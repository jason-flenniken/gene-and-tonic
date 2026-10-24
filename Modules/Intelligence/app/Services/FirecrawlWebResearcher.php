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

        $body = [
            'query' => $query->query,
            'limit' => $query->limit,
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
            : (int) ceil($query->limit / 10) * 2 + ($query->scrape ? count($results) : 0);

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

            $url = trim($result['url']);
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));

            if (! in_array($scheme, ['http', 'https'], true) || $host === '' || ! $this->hostAllowed($host, $query->domains)) {
                continue;
            }

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
     * @param  list<string>  $domains
     */
    private function hostAllowed(string $host, array $domains): bool
    {
        if ($domains === []) {
            return true;
        }

        foreach ($domains as $domain) {
            $domain = strtolower($domain);

            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
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
