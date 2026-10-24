<?php

return [
    'name' => 'Genetics',

    /*
    | Largest genetic upload accepted, in kilobytes. Livewire's temporary-upload
    | rule in config/livewire.php reads the same env value.
    */
    'max_upload_kb' => (int) env('GENETICS_MAX_UPLOAD_KB', 25600),

    /*
    | Most rows a marker list (or extracted PDF) may hold. Every row is stored and
    | an unknown rsID creates a shared pending reference row, so a longer list
    | fails before anything is written. Raw DNA exports have no such cap.
    */
    'max_marker_rows' => (int) env('GENETICS_MAX_MARKER_ROWS', 1000),

    /*
    | AI profile used to turn scraped SNP pages into a reference row. Registered
    | with Intelligence as "genetics.lookup" when the module boots.
    */
    'lookup_profile' => [
        'provider' => env('AI_GENETICS_LOOKUP_PROVIDER', 'openai'),
        'model' => env('AI_GENETICS_LOOKUP_MODEL', 'gpt-6-luna'),
        'effort' => env('AI_GENETICS_LOOKUP_EFFORT', 'low'),
        'max_tokens' => (int) env('AI_GENETICS_LOOKUP_MAX_TOKENS', 3000),
        'timeout' => (int) env('AI_GENETICS_LOOKUP_TIMEOUT', 60),
        'instructions' => 'genetics::prompts.lookup-instructions',
        'prompt' => 'genetics::prompts.lookup',
    ],

    /*
    | AI profile used to pull markers out of a pdf or unrecognised file.
    | Registered with Intelligence as "genetics.extract" when the module boots.
    */
    'extract_profile' => [
        'provider' => env('AI_GENETICS_EXTRACT_PROVIDER', 'openai'),
        'model' => env('AI_GENETICS_EXTRACT_MODEL', 'gpt-6-luna'),
        'effort' => env('AI_GENETICS_EXTRACT_EFFORT', 'low'),
        'max_tokens' => (int) env('AI_GENETICS_EXTRACT_MAX_TOKENS', 4000),
        'timeout' => (int) env('AI_GENETICS_EXTRACT_TIMEOUT', 60),
        'instructions' => 'genetics::prompts.extract-instructions',
        'prompt' => 'genetics::prompts.extract',
    ],

    'extract' => [
        // Most characters of marker lines sent to the model in one call.
        'max_chars' => (int) env('GENETICS_EXTRACT_MAX_CHARS', 20000),
    ],

    'lookup' => [
        'domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('GENETICS_LOOKUP_DOMAINS', 'snpedia.com,ncbi.nlm.nih.gov,ebi.ac.uk')),
        ))),
        'limit' => 5,
    ],

    /*
    | Most lookups one upload queues. The rest of its unknown SNPs stay pending
    | until someone presses Look up, which bounds search and model spend.
    */
    'lookup_cap' => (int) env('GENETICS_LOOKUP_CAP', 15),
];
