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

    // Write an ai_requests row for each provider call.
    'log_requests' => true,
];
