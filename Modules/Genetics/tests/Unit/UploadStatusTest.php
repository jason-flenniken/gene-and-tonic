<?php

use Modules\Genetics\Enums\UploadStatus;

it('stores snake-case values', function () {
    expect(array_map(fn (UploadStatus $s): string => $s->value, UploadStatus::cases()))
        ->toBe(['queued', 'parsing', 'looking_up', 'complete', 'failed']);
});

it('is active until complete or failed', function () {
    expect(array_values(array_filter(UploadStatus::cases(), fn (UploadStatus $s): bool => $s->isActive())))
        ->toBe([UploadStatus::Queued, UploadStatus::Parsing, UploadStatus::LookingUp]);
});

it('has readable labels', function () {
    expect(UploadStatus::LookingUp->label())->toBe('Looking up')
        ->and(UploadStatus::Queued->label())->toBe('Queued');
});

it('maps each status to a tone', function () {
    expect(array_map(fn (UploadStatus $s): string => $s->tone(), UploadStatus::cases()))
        ->toBe(['active', 'active', 'active', 'ok', 'bad']);
});
