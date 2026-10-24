<?php

/*
| Reports may read Supplements only through its public Contracts, Data, Enums and
| Testing namespaces, and never talks to Intelligence.
*/
arch('reports does not use private Supplements namespaces or Intelligence')
    ->expect('Modules\Reports')
    ->not->toUse([
        'Modules\Supplements\Models',
        'Modules\Supplements\Repositories',
        'Modules\Supplements\Actions',
        'Modules\Supplements\Jobs',
        'Modules\Supplements\Support',
        'Modules\Supplements\Livewire',
        'Modules\Supplements\Providers',
        'Modules\Intelligence',
    ]);
