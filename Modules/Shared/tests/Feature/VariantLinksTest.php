<?php

use Modules\Shared\Contracts\VariantLinks;
use Modules\Shared\Data\LinkedSupplementData;
use Modules\Shared\Services\NoVariantLinks;
use Modules\Shared\Testing\FakeVariantLinks;

it('links no variants by default', function () {
    expect((new NoVariantLinks)->forUser(1))->toBe([]);
});

it('serializes a linked supplement as its id and name only', function () {
    expect((new LinkedSupplementData(5, 'Methylfolate'))->toArray())->toBe(['supplementId' => 5, 'name' => 'Methylfolate']);
});

it('binds the fake as the variant links and returns the stored map per user', function () {
    $map = ['rs1801131' => [new LinkedSupplementData(5, 'Methylfolate')]];

    $fake = FakeVariantLinks::install()->for(3, $map);

    expect(app(VariantLinks::class))->toBe($fake)
        ->and(app(VariantLinks::class)->forUser(3))->toBe($map)
        ->and(app(VariantLinks::class)->forUser(4))->toBe([]);
});
