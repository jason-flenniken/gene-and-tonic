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

/*
| Reports may read Recommendations only through its public Contracts, Data and Testing
| namespaces.
*/
arch('reports does not use private Recommendations namespaces')
    ->expect('Modules\Reports')
    ->not->toUse([
        'Modules\Recommendations\Services',
        'Modules\Recommendations\Models',
        'Modules\Recommendations\Actions',
        'Modules\Recommendations\Jobs',
        'Modules\Recommendations\Livewire',
        'Modules\Recommendations\Listeners',
        'Modules\Recommendations\Providers',
    ]);
