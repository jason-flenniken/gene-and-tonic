<?php

declare(strict_types=1);

/*
 * Only overrides live here; Boost merges in its package defaults for every other key.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Agent Overrides
    |--------------------------------------------------------------------------
    |
    | Keep Boost's guidelines in AGENTS.md even though CLAUDE.md exists, so
    | they aren't duplicated into CLAUDE.md. CLAUDE.md imports AGENTS.md.
    |
    */

    'agents' => [
        'claude_code' => [
            'guidelines_path' => 'AGENTS.md',
        ],
    ],

];
