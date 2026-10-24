<?php

namespace Modules\Intelligence\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Intelligence\Contracts\WebResearcher;
use Modules\Intelligence\Data\SearchQuery;
use Modules\Intelligence\Data\SourceDocument;
use Modules\Intelligence\Exceptions\ResearchProviderException;
use Throwable;

/**
 * Firecrawl v2 /search over Laravel's Http client (no SDK). Results are untrusted: only http(s)
 * URLs on the requested domains survive.
 */
class FirecrawlWebResearcher implements WebResearcher
{
    public function __construct(private readonly UsageLogger $logger) {}

    public function search(SearchQuery $query): array
    {
        $start = hrtime(true);

        try {
            [$documents, $credits] = $this->perform($query);
        } catch (Throwable $e) {
            $this->logger->logSearch($query, null, $e, $this->elapsedMs($start), null);

            throw $e;
        }

        $this->logger->logSearch($query, count($documents), null, $this->elapsedMs($start), $credits);

        return $documents;
    }

    /**
     * @return array{0: list<SourceDocument>, 1: int}
     */
    private function perform(SearchQuery $query): array
    {
        $key = config('intelligence.research.firecrawl.key');

        if (! is_string($key) || $key === '') {
            throw ResearchProviderException::because('Firecrawl API key is not configured');
        }

        $url = rtrim((string) config('intelligence.research.firecrawl.url', 'https://api.firecrawl.dev'), '/').'/v2/search';

        $limit = max(1, min($query->limit, max(1, (int) config('intelligence.research.max_results', 8))));

        $body = [
            'query' => $query->query,
            'limit' => $limit,
            'includeDomains' => $query->domains,
        ];

        if ($query->scrape) {
            $body['scrapeOptions'] = ['formats' => ['markdown']];
        }

        try {
            $response = Http::withToken($key)
                ->timeout((int) config('intelligence.research.firecrawl.timeout', 60))
                ->acceptJson()
                ->post($url, $body);
        } catch (ConnectionException) {
            throw ResearchProviderException::because('Firecrawl request timed out or could not connect');
        }

        if ($response->failed()) {
            throw ResearchProviderException::because("Firecrawl request failed with HTTP status {$response->status()}");
        }

        if ($response->json('success') !== true) {
            throw ResearchProviderException::because('Firecrawl reported an unsuccessful search');
        }

        $results = $response->json('data.web');
        $results = is_array($results) ? $results : [];

        $reported = $response->json('creditsUsed');
        $credits = is_int($reported) && $reported >= 0
            ? $reported
            : (int) ceil($limit / 10) * 2 + ($query->scrape ? count($results) : 0);

        return [$this->map($results, $query), $credits];
    }

    /**
     * @param  array<mixed>  $results
     * @return list<SourceDocument>
     */
    private function map(array $results, SearchQuery $query): array
    {
        $max = max(0, (int) config('intelligence.research.max_source_chars', 6000));
        $seen = [];
        $documents = [];

        foreach ($results as $result) {
            if (! is_array($result) || ! is_string($result['url'] ?? null)) {
                continue;
            }

            $normalised = $this->normaliseUrl($result['url'], $query->domains);

            if ($normalised === null) {
                continue;
            }

            [$url, $host] = $normalised;

            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $description = $this->text($result['description'] ?? null);
            $markdown = $this->text($result['markdown'] ?? null) ?? $description ?? '';

            $documents[] = new SourceDocument(
                id: 'S'.(count($documents) + 1),
                url: $url,
                title: $this->text($result['title'] ?? null) ?? $url,
                domain: $host,
                description: $description,
                markdown: mb_substr($markdown, 0, $max),
            );
        }

        return $documents;
    }

    /**
     * Validate an untrusted result URL and rebuild it from its parsed parts. URLs that parse differently
     * in PHP and in browsers (backslash, whitespace, control characters, userinfo) are rejected outright.
     *
     * @param  list<string>  $domains
     * @return array{0: string, 1: string}|null [normalised url, host]
     */
    private function normaliseUrl(string $raw, array $domains): ?array
    {
        $raw = trim($raw);

        if (preg_match('/[\x00-\x20\x7f\\\\]/', $raw) === 1 || stripos($raw, '%5c') !== false) {
            return null;
        }

        $parts = parse_url($raw);

        $scheme = $parts === false ? '' : strtolower($parts['scheme'] ?? '');

        if ($parts === false
            || ! in_array($scheme, ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = rtrim(strtolower((string) ($parts['host'] ?? '')), '.');

        if ($host === '' || ! $this->hostAllowed($host, $domains)) {
            return null;
        }

        $url = $scheme.'://'.$host
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '')
            .(isset($parts['query']) ? '?'.$parts['query'] : '');

        return [$url, $host];
    }

    /**
     * @param  list<string>  $domains
     */
    private function hostAllowed(string $host, array $domains): bool
    {
        if ($domains === []) {
            return true;
        }

        foreach ($domains as $domain) {
            $domain = rtrim(strtolower(trim($domain)), '.');

            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                return true;
            }
        }

        return false;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function elapsedMs(int|float $start): int
    {
        return (int) round((hrtime(true) - $start) / 1_000_000);
    }
}
