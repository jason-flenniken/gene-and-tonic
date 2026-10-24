<?php

use Modules\Genetics\Enums\LookupStatus;

it('stores snake-case values', function () {
    expect(array_map(fn (LookupStatus $s): string => $s->value, LookupStatus::cases()))
        ->toBe(['seeded', 'pending', 'looking_up', 'complete', 'failed']);
});

it('is active only while looking up', function () {
    expect(array_values(array_filter(LookupStatus::cases(), fn (LookupStatus $s): bool => $s->isActive())))
        ->toBe([LookupStatus::LookingUp]);
});

it('has readable labels', function () {
    expect(LookupStatus::LookingUp->label())->toBe('Looking up')
        ->and(LookupStatus::Seeded->label())->toBe('Seeded');
});
