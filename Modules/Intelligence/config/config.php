<?php

return [
    'name' => 'Intelligence',

    /*
     * Named generation profiles, keyed by name. Other modules contribute theirs through
     * ProfileRegistry::register() or their own config. Shape:
     * ['provider', 'model', 'effort' (nullable), 'max_tokens', 'timeout', 'instructions' (view), 'prompt' (view)]
     */
    'profiles' => [],

    'pricing' => [
        // USD per 1M tokens. Unknown models cost 0.
        'models' => [
            'gpt-6.1-sol' => ['input' => 2.00, 'output' => 10.00],
            'gpt-6-luna' => ['input' => 0.10, 'output' => 0.50],
            'gpt-6-astra' => ['input' => 10.00, 'output' => 50.00],
        ],
        'firecrawl_credit_usd' => 0.001,
    ],

    'research' => [
        'driver' => env('RESEARCH_DRIVER', 'firecrawl'),
        'firecrawl' => [
            'url' => env('FIRECRAWL_API_URL', 'https://api.firecrawl.dev'),
            'key' => env('FIRECRAWL_API_KEY'),
            'timeout' => (int) env('FIRECRAWL_TIMEOUT', 60),
        ],
        // Hard cap on Firecrawl results requested per search, whatever the caller asks for.
        'max_results' => (int) env('FIRECRAWL_MAX_RESULTS', 8),
        // Scraped markdown is truncated to this many characters per source.
        'max_source_chars' => 6000,
    ],

    // Write an ai_requests row for each provider call.
    'log_requests' => (bool) env('AI_LOG_REQUESTS', true),
];
