<?php

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\UploadStatus;
use Modules\Genetics\Models\GeneticUpload;
use Modules\Genetics\Models\GeneVariant;

beforeEach(function () {
    Storage::fake('genetics');
    config(['genetics.reap_after_minutes' => 15]);
    $this->user = User::factory()->create();
});

/** Creates an upload whose updated_at is the given number of minutes ago. */
function uploadUpdatedMinutesAgo(User $user, int $minutes, UploadStatus $status, ?string $path): GeneticUpload
{
    return GeneticUpload::factory()->create([
        'user_id' => $user->id,
        'status' => $status,
        'path' => $path,
        'updated_at' => now()->subMinutes($minutes),
    ]);
}

/** Creates a reference row whose updated_at is the given number of minutes ago. */
function variantUpdatedMinutesAgo(int $minutes, LookupStatus $status): GeneVariant
{
    return GeneVariant::factory()->create([
        'status' => $status,
        'updated_at' => now()->subMinutes($minutes),
    ]);
}

it('fails a stale active upload and deletes its raw file', function (UploadStatus $status) {
    Storage::disk('genetics')->put('uploads/stale.txt', 'rs1801133 AG');
    $upload = uploadUpdatedMinutesAgo($this->user, 20, $status, 'uploads/stale.txt');

    $this->artisan('genetics:reap-stale')->assertSuccessful();

    $upload->refresh();
    expect($upload->status)->toBe(UploadStatus::Failed)
        ->and($upload->error)->toBe('Timed out, try again.')
        ->and($upload->path)->toBeNull();
    Storage::disk('genetics')->assertMissing('uploads/stale.txt');
})->with([
    'queued' => UploadStatus::Queued,
    'parsing' => UploadStatus::Parsing,
    'looking up' => UploadStatus::LookingUp,
]);

it('leaves a fresh active upload and its raw file untouched', function () {
    Storage::disk('genetics')->put('uploads/fresh.txt', 'rs1801133 AG');
    $upload = uploadUpdatedMinutesAgo($this->user, 5, UploadStatus::Parsing, 'uploads/fresh.txt');

    $this->artisan('genetics:reap-stale')->assertSuccessful();

    $upload->refresh();
    expect($upload->status)->toBe(UploadStatus::Parsing)
        ->and($upload->error)->toBeNull()
        ->and($upload->path)->toBe('uploads/fresh.txt');
    Storage::disk('genetics')->assertExists('uploads/fresh.txt');
});

it('leaves completed and failed uploads untouched however old they are', function (UploadStatus $status) {
    Storage::disk('genetics')->put('uploads/done.txt', 'rs1801133 AG');
    $upload = uploadUpdatedMinutesAgo($this->user, 600, $status, 'uploads/done.txt');

    $this->artisan('genetics:reap-stale')->assertSuccessful();

    expect($upload->refresh()->status)->toBe($status)
        ->and($upload->path)->toBe('uploads/done.txt');
    Storage::disk('genetics')->assertExists('uploads/done.txt');
})->with([
    'complete' => UploadStatus::Complete,
    'failed' => UploadStatus::Failed,
]);

it('fails a stale upload that has no raw file left', function () {
    $upload = uploadUpdatedMinutesAgo($this->user, 20, UploadStatus::Queued, null);

    $this->artisan('genetics:reap-stale')->assertSuccessful();

    expect($upload->refresh()->status)->toBe(UploadStatus::Failed)
        ->and($upload->error)->toBe('Timed out, try again.');
});

it('fails a stale looking-up reference row so its button comes back', function () {
    $variant = variantUpdatedMinutesAgo(20, LookupStatus::LookingUp);

    $this->artisan('genetics:reap-stale')->assertSuccessful();

    $variant->refresh();
    expect($variant->status)->toBe(LookupStatus::Failed)
        ->and($variant->error)->toBe('Timed out, try again.');
});

it('leaves a fresh looking-up reference row untouched', function () {
    $variant = variantUpdatedMinutesAgo(5, LookupStatus::LookingUp);

    $this->artisan('genetics:reap-stale')->assertSuccessful();

    expect($variant->refresh()->status)->toBe(LookupStatus::LookingUp)
        ->and($variant->error)->toBeNull();
});

it('leaves seeded and complete reference rows untouched however old they are', function (LookupStatus $status) {
    $variant = variantUpdatedMinutesAgo(600, $status);

    $this->artisan('genetics:reap-stale')->assertSuccessful();

    expect($variant->refresh()->status)->toBe($status);
})->with([
    'seeded' => LookupStatus::Seeded,
    'complete' => LookupStatus::Complete,
]);

it('uses the configured age, not a hard-coded one', function () {
    config(['genetics.reap_after_minutes' => 60]);
    $upload = uploadUpdatedMinutesAgo($this->user, 30, UploadStatus::Queued, null);

    $this->artisan('genetics:reap-stale')->assertSuccessful();

    expect($upload->refresh()->status)->toBe(UploadStatus::Queued);
});

it('is scheduled every five minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'genetics:reap-stale'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *');
});
