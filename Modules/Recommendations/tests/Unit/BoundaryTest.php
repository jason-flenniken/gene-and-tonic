<?php

/*
| Recommendations may use only the public namespaces (Contracts, Data, Enums,
| Exceptions, Testing) of Genetics, Supplements and Shared. It never talks to
| Intelligence or Reports.
*/
arch('recommendations uses only the public namespaces of Genetics and Supplements')
    ->expect('Modules\Recommendations')
    ->not->toUse([
        'Modules\Genetics\Models',
        'Modules\Genetics\Repositories',
        'Modules\Genetics\Actions',
        'Modules\Genetics\Jobs',
        'Modules\Genetics\Support',
        'Modules\Genetics\Livewire',
        'Modules\Genetics\Providers',
        'Modules\Supplements\Models',
        'Modules\Supplements\Repositories',
        'Modules\Supplements\Actions',
        'Modules\Supplements\Jobs',
        'Modules\Supplements\Support',
        'Modules\Supplements\Livewire',
        'Modules\Supplements\Providers',
        'Modules\Shared\Services',
        'Modules\Shared\Providers',
        'Modules\Intelligence',
        'Modules\Reports',
    ]);
