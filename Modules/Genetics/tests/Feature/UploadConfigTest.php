<?php

it('keeps the genetics disk private and unserved', function () {
    $disk = config('filesystems.disks.genetics');

    expect($disk)->toBeArray()
        ->and($disk['driver'])->toBe('local')
        ->and($disk['root'])->toBe(storage_path('app/genetics'))
        ->and($disk['throw'])->toBeTrue()
        ->and($disk)->not->toHaveKey('serve')
        ->and($disk)->not->toHaveKey('url');
});

it('sends Livewire temporary uploads to the genetics disk with the configured max', function () {
    expect(config('livewire.temporary_file_upload.disk'))->toBe('genetics')
        ->and(config('livewire.temporary_file_upload.rules'))->toBe(['required', 'file', 'max:25600'])
        ->and(config('genetics.max_upload_kb'))->toBe(25600);
});
