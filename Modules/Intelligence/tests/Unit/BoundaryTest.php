<?php

/*
| Intelligence holds no domain knowledge: it never uses Genetics or Recommendations.
*/
arch('intelligence does not use Genetics or Recommendations')
    ->expect('Modules\Intelligence')
    ->not->toUse(['Modules\Genetics', 'Modules\Recommendations']);
