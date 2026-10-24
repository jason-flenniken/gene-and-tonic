<?php

use Modules\Shared\Contracts\PageActions;
use Modules\Shared\Services\InMemoryPageActions;

it('binds the page action registry as a shared singleton', function () {
    $first = app(PageActions::class);

    $first->register('testing.binding', 'bound::view');

    expect($first)->toBeInstanceOf(InMemoryPageActions::class)
        ->and(app(PageActions::class))->toBe($first)
        ->and(app(PageActions::class)->for('testing.binding'))->toBe(['bound::view']);
});
