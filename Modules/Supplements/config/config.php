<?php

return [
    'name' => 'Supplements',

    /*
    | AI profile used to analyse scraped sources. Registered with Intelligence as
    | "supplements.research" when the module boots.
    */
    'research_profile' => [
        'provider' => env('AI_RESEARCH_PROVIDER', 'openai'),
        'model' => env('AI_RESEARCH_MODEL', 'gpt-6.1-sol'),
        'effort' => env('AI_RESEARCH_EFFORT', 'medium'),
        'max_tokens' => (int) env('AI_RESEARCH_MAX_TOKENS', 8000),
        'timeout' => (int) env('AI_RESEARCH_TIMEOUT', 120),
        'instructions' => 'supplements::prompts.research-instructions',
        'prompt' => 'supplements::prompts.research',
    ],

    'research' => [
        'domains' => [
            'pubmed.ncbi.nlm.nih.gov',
            'pmc.ncbi.nlm.nih.gov',
            'ods.od.nih.gov',
            'cochranelibrary.com',
            'nccih.nih.gov',
        ],
        'limit' => 8,
        'gene_query' => '%s gene polymorphism nutrigenomics',
        'gene_limit' => (int) env('RESEARCH_GENE_LIMIT', 4),
    ],
];
