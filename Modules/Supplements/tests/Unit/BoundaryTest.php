<?php

arch()->expect('Modules\Supplements')->not->toUse('Modules\Reports');

arch('supplements does not use Genetics or Recommendations')
    ->expect('Modules\Supplements')
    ->not->toUse(['Modules\Genetics', 'Modules\Recommendations']);
