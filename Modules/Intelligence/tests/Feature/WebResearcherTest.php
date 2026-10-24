<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Intelligence\Contracts\WebResearcher;
use Modules\Intelligence\Data\SearchQuery;
use Modules\Intelligence\Data\SourceDocument;
use Modules\Intelligence\Exceptions\ResearchProviderException;
use Modules\Intelligence\Models\AiRequest;
use Modules\Intelligence\Services\UsageLogger;
use Modules\Intelligence\Testing\FakeWebResearcher;

const RESEARCH_DOMAINS = ['pubmed.ncbi.nlm.nih.gov', 'ods.od.nih.gov'];

function researchQuery(): SearchQuery
{
    return new SearchQuery('magnesium glycinate supplement clinical evidence', RESEARCH_DOMAINS, 8, true);
}

/**
 * @param  list<array<string, mixed>>  $results
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function firecrawlBody(array $results, array $extra = []): array
{
    return array_merge(['success' => true, 'data' => ['web' => $results]], $extra);
}

beforeEach(function () {
    config([
        'intelligence.research.firecrawl.key' => 'fc-test-key',
        'intelligence.research.firecrawl.url' => 'https://api.firecrawl.dev',
        'intelligence.research.max_source_chars' => 10,
    ]);
});

it('sends the documented body and maps results to source documents', function () {
    Http::fake(['api.firecrawl.dev/*' => Http::response(firecrawlBody([
        ['url' => 'https://pubmed.ncbi.nlm.nih.gov/1/', 'title' => 'One', 'description' => 'd1', 'markdown' => '0123456789ABCDEF'],
        ['url' => 'https://ods.od.nih.gov/factsheets/Magnesium', 'title' => 'Two', 'description' => 'd2', 'markdown' => 'short'],
        ['url' => 'https://www.pubmed.ncbi.nlm.nih.gov/3/', 'title' => '', 'markdown' => null, 'description' => 'only desc'],
    ], ['creditsUsed' => 5]), 200)]);

    $docs = app(WebResearcher::class)->search(researchQuery());

    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.firecrawl.dev/v2/search'
        && $r->hasHeader('Authorization', 'Bearer fc-test-key')
        && $r['query'] === 'magnesium glycinate supplement clinical evidence'
        && $r['includeDomains'] === RESEARCH_DOMAINS
        && $r['limit'] === 8
        && $r['scrapeOptions']['formats'] === ['markdown']);

    expect($docs)->toHaveCount(3)
        ->and($docs[0])->toBeInstanceOf(SourceDocument::class)
        ->and(array_map(fn (SourceDocument $d): string => $d->id, $docs))->toBe(['S1', 'S2', 'S3'])
        ->and($docs[0]->domain)->toBe('pubmed.ncbi.nlm.nih.gov')
        ->and($docs[0]->markdown)->toBe('0123456789')
        ->and($docs[1]->markdown)->toBe('short')
        ->and($docs[2]->markdown)->toBe('only desc')
        ->and($docs[2]->title)->toBe('https://www.pubmed.ncbi.nlm.nih.gov/3/');

    $row = AiRequest::sole();
    expect($row->operation)->toBe('search')
        ->and($row->provider)->toBe('firecrawl')
        ->and($row->status)->toBe('success')
        ->and($row->credits)->toBe(5)
        ->and((float) $row->estimated_cost_usd)->toBe(0.005);
});

it('estimates credits when the response omits creditsUsed', function () {
    Http::fake(['api.firecrawl.dev/*' => Http::response(firecrawlBody([
        ['url' => 'https://pubmed.ncbi.nlm.nih.gov/1/', 'title' => 'One', 'markdown' => 'x'],
    ]), 200)]);

    app(WebResearcher::class)->search(researchQuery());

    expect(AiRequest::sole()->credits)->toBe(3);
});

it('drops non-http urls, off-domain hosts and duplicates', function () {
    Http::fake(['api.firecrawl.dev/*' => Http::response(firecrawlBody([
        ['url' => 'javascript:alert(1)', 'title' => 'x', 'markdown' => 'x'],
        ['url' => 'https://evil.example.com/pubmed.ncbi.nlm.nih.gov', 'title' => 'x', 'markdown' => 'x'],
        ['url' => 'https://pubmed.ncbi.nlm.nih.gov.evil.com/', 'title' => 'x', 'markdown' => 'x'],
        ['url' => 'https://pubmed.ncbi.nlm.nih.gov/1/', 'title' => 'ok', 'markdown' => 'x'],
        ['url' => 'https://pubmed.ncbi.nlm.nih.gov/1/', 'title' => 'dupe', 'markdown' => 'x'],
    ]), 200)]);

    $docs = app(WebResearcher::class)->search(researchQuery());

    expect($docs)->toHaveCount(1)
        ->and($docs[0]->id)->toBe('S1')
        ->and($docs[0]->title)->toBe('ok');
});

it('fails without a key and sends no request', function () {
    config(['intelligence.research.firecrawl.key' => '']);
    Http::fake();

    expect(fn () => app(WebResearcher::class)->search(researchQuery()))
        ->toThrow(ResearchProviderException::class, 'Firecrawl API key is not configured');

    Http::assertNothingSent();
});

it('maps failures to ResearchProviderException and logs a failed row', function (string $kind) {
    Http::fake(['api.firecrawl.dev/*' => match ($kind) {
        '429' => Http::response(['error' => 'rate fc-test-key'], 429),
        '500' => Http::response('boom', 500),
        'success false' => Http::response(['success' => false, 'error' => 'x'], 200),
        'timeout' => Http::failedConnection('timed out fc-test-key'),
    }]);

    expect(fn () => app(WebResearcher::class)->search(researchQuery()))
        ->toThrow(ResearchProviderException::class);

    $row = AiRequest::sole();
    expect($row->operation)->toBe('search')
        ->and($row->status)->toBe('failed')
        ->and($row->error)->not->toBeEmpty()
        ->and(json_encode($row->toArray()))->not->toContain('fc-test-key');
})->with(['429', '500', 'success false', 'timeout']);

it('never leaks the api key into the exception message', function () {
    Http::fake(['api.firecrawl.dev/*' => Http::response(['error' => 'bad key fc-test-key'], 401)]);

    try {
        app(WebResearcher::class)->search(researchQuery());
        $this->fail('Expected ResearchProviderException');
    } catch (ResearchProviderException $e) {
        expect($e->getMessage())->not->toContain('fc-test-key')
            ->and($e->getPrevious())->toBeNull();
    }
});

it('scrubs the firecrawl key from logged errors', function () {
    expect(app(UsageLogger::class)->scrub('failed with Bearer fc-test-key now'))->not->toContain('fc-test-key');
});

it('throws a clear error for an unknown driver', function () {
    config(['intelligence.research.driver' => 'nope']);

    app(WebResearcher::class);
})->throws(ResearchProviderException::class, 'Unknown research driver [nope]');

it('provides a fake researcher', function () {
    $doc = new SourceDocument('S1', 'https://pubmed.ncbi.nlm.nih.gov/1/', 't', 'pubmed.ncbi.nlm.nih.gov', null, 'md');
    $fake = FakeWebResearcher::install()->returning([$doc]);

    expect(app(WebResearcher::class)->search(researchQuery()))->toBe([$doc])
        ->and($fake->queries())->toHaveCount(1);

    $fake->failWith('nope');
    expect(fn () => app(WebResearcher::class)->search(researchQuery()))->toThrow(ResearchProviderException::class, 'nope');
});

it('drops urls that parse differently in browsers or leave the allowed domains', function (string $url, array $domains) {
    Http::fake(['api.firecrawl.dev/*' => Http::response(firecrawlBody([
        ['url' => $url, 'title' => 'x', 'markdown' => 'x'],
    ]), 200)]);

    $query = new SearchQuery('q', $domains, 8, true);

    expect(app(WebResearcher::class)->search($query))->toBe([]);
})->with([
    'backslash before userinfo' => ['https://evil.com\\@pubmed.ncbi.nlm.nih.gov/', RESEARCH_DOMAINS],
    'encoded backslash' => ['https://evil.com%5c@pubmed.ncbi.nlm.nih.gov/', RESEARCH_DOMAINS],
    'encoded backslash uppercase' => ['https://evil.com%5C@pubmed.ncbi.nlm.nih.gov/', RESEARCH_DOMAINS],
    'embedded tab' => ["https://evil.com\t@pubmed.ncbi.nlm.nih.gov/", RESEARCH_DOMAINS],
    'userinfo' => ['https://evil.com@pubmed.ncbi.nlm.nih.gov/', RESEARCH_DOMAINS],
    'suffix without dot boundary' => ['https://evilnih.gov/x', ['nih.gov']],
    'suffix without dot boundary on subdomain' => ['https://evilods.od.nih.gov/x', RESEARCH_DOMAINS],
    'mixed-case javascript scheme' => ['JavaScript:alert(1)', RESEARCH_DOMAINS],
    'allowed host as subdomain of attacker' => ['https://pubmed.ncbi.nlm.nih.gov.evil.com/', RESEARCH_DOMAINS],
]);

it('keeps allowed urls and stores a normalised form', function (string $raw, string $expected) {
    Http::fake(['api.firecrawl.dev/*' => Http::response(firecrawlBody([
        ['url' => $raw, 'title' => 'x', 'markdown' => 'x'],
    ]), 200)]);

    $docs = app(WebResearcher::class)->search(researchQuery());

    expect($docs)->toHaveCount(1)
        ->and($docs[0]->url)->toBe($expected)
        ->and($docs[0]->domain)->toBe('pubmed.ncbi.nlm.nih.gov');
})->with([
    'case' => ['HTTPS://PubMed.NCBI.nlm.nih.gov/12345678/', 'https://pubmed.ncbi.nlm.nih.gov/12345678/'],
    'trailing dot' => ['https://pubmed.ncbi.nlm.nih.gov./x', 'https://pubmed.ncbi.nlm.nih.gov/x'],
    'port' => ['https://pubmed.ncbi.nlm.nih.gov:443/x', 'https://pubmed.ncbi.nlm.nih.gov:443/x'],
    'query and fragment' => ['https://pubmed.ncbi.nlm.nih.gov/x?term=mg#top', 'https://pubmed.ncbi.nlm.nih.gov/x?term=mg'],
]);

it('caps the requested limit at the configured maximum and floors it at one', function (int $asked, int $sent) {
    Http::fake(['api.firecrawl.dev/*' => Http::response(firecrawlBody([]), 200)]);

    app(WebResearcher::class)->search(new SearchQuery('q', RESEARCH_DOMAINS, $asked, false));

    Http::assertSent(fn (Request $r): bool => $r['limit'] === $sent);
})->with([
    'huge' => [1000, 8],
    'zero' => [0, 1],
    'negative' => [-5, 1],
    'within range' => [3, 3],
]);
