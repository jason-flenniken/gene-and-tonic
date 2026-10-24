<?php

/*
| Genetics may use only Intelligence's public Contracts, Data, Exceptions and
| Testing namespaces, plus Shared. It never touches Supplements,
| Recommendations or Reports.
*/
arch('genetics does not use private Intelligence namespaces or peer domain modules')
    ->expect('Modules\Genetics')
    ->not->toUse([
        'Modules\Intelligence\Services',
        'Modules\Intelligence\Models',
        'Modules\Intelligence\Repositories',
        'Modules\Intelligence\Providers',
        'Modules\Supplements',
        'Modules\Recommendations',
        'Modules\Reports',
    ]);
