<?php

use Modules\Shared\Services\InMemoryPageActions;

it('lists the views registered for a slot in registration order', function () {
    $actions = new InMemoryPageActions;

    $actions->register('supplement.header', 'first::view');
    $actions->register('supplement.header', 'second::view');
    $actions->register('other.slot', 'elsewhere::view');

    expect($actions->for('supplement.header'))->toBe(['first::view', 'second::view'])
        ->and($actions->for('other.slot'))->toBe(['elsewhere::view']);
});

it('does not list the same view twice in a slot', function () {
    $actions = new InMemoryPageActions;

    $actions->register('supplement.header', 'dupe::view');
    $actions->register('supplement.header', 'dupe::view');

    expect($actions->for('supplement.header'))->toBe(['dupe::view']);
});

it('returns an empty list for a slot with nothing registered', function () {
    expect((new InMemoryPageActions)->for('missing.slot'))->toBe([]);
});
