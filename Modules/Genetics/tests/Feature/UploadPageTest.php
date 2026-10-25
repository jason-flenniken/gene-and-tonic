<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Genetics\Database\Seeders\GeneVariantSeeder;
use Modules\Genetics\Enums\UploadStatus;
use Modules\Genetics\Jobs\ProcessGeneticUpload;
use Modules\Genetics\Models\GeneticUpload;
use Modules\Genetics\Models\UserVariant;

beforeEach(function () {
    $this->withoutVite();
    // In tests Livewire keeps temporary uploads on `tmp-for-tests` instead of the configured disk.
    Storage::fake('tmp-for-tests');
    Storage::fake('genetics');
    $this->user = User::factory()->create();
});

function uploadFixture(string $fixture, string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, (string) file_get_contents(__DIR__.'/../Fixtures/'.$fixture));
}

/** @return list<string> */
function allGeneticFiles(): array
{
    return [...Storage::disk('genetics')->allFiles(), ...Storage::disk('tmp-for-tests')->allFiles()];
}

it('stores a valid upload on the genetics disk, creates a queued row and dispatches the job', function () {
    Queue::fake();

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('file', uploadFixture('23andme.txt', 'genome.txt'))
        ->call('uploadResults')
        ->assertHasNoErrors()
        ->assertSet('file', null);

    $upload = GeneticUpload::sole();
    expect($upload->user_id)->toBe($this->user->id)
        ->and($upload->original_name)->toBe('genome.txt')
        ->and($upload->status)->toBe(UploadStatus::Queued)
        ->and($upload->path)->toMatch('/^uploads\/[0-9a-f-]{36}\.txt$/')
        ->and(Storage::disk('genetics')->allFiles())->toBe([$upload->path])
        ->and(Storage::disk('tmp-for-tests')->allFiles())->toBe([]);

    Queue::assertPushed(ProcessGeneticUpload::class, fn (ProcessGeneticUpload $job): bool => $job->uploadId === $upload->id);
});

it('rejects a disallowed or oversized file without creating a row or dispatching', function (UploadedFile $file) {
    Queue::fake();

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('file', $file)
        ->call('uploadResults')
        ->assertHasErrors('file');

    expect(GeneticUpload::count())->toBe(0)
        ->and(allGeneticFiles())->toBe([]);
    Queue::assertNothingPushed();
})->with([
    'an .exe' => fn () => UploadedFile::fake()->createWithContent('setup.exe', "MZ\x90\x00\x03\x00\x00\x00binary"),
    'plain text named .exe' => fn () => UploadedFile::fake()->createWithContent('genome.exe', "rs1801133\t1\t1\tAG\n"),
    'over the max' => fn () => UploadedFile::fake()->create('genome.txt', 25601, 'text/plain'),
]);

it('cuts a long original file name to 255 characters', function () {
    Queue::fake();

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('file', uploadFixture('23andme.txt', str_repeat('a', 300).'.txt'))
        ->call('uploadResults')
        ->assertHasNoErrors();

    expect(mb_strlen(GeneticUpload::sole()->original_name))->toBe(255);
});

it('rate-limits the sixth upload in a minute', function () {
    Queue::fake();
    $component = Livewire::actingAs($this->user)->test('genetics::profile');

    foreach (range(1, 5) as $i) {
        $component->set('file', uploadFixture('23andme.txt', "genome-{$i}.txt"))->call('uploadResults')->assertHasNoErrors();
    }

    $component->set('file', uploadFixture('23andme.txt', 'genome-6.txt'))
        ->call('uploadResults')
        ->assertHasErrors(['file' => 'Too many requests, try again in a minute.']);

    expect(GeneticUpload::count())->toBe(5)
        ->and(Storage::disk('tmp-for-tests')->allFiles())->toBe([]);
    Queue::assertPushed(ProcessGeneticUpload::class, 5);
});

it('processes an upload end to end and leaves no file behind, on success and on failure', function (string $fixture, UploadStatus $status, int $stored) {
    $this->seed(GeneVariantSeeder::class);

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->set('file', uploadFixture($fixture, 'upload.txt'))
        ->call('uploadResults')
        ->assertHasNoErrors();

    expect(GeneticUpload::sole()->status)->toBe($status)
        ->and(UserVariant::count())->toBe($stored)
        ->and(allGeneticFiles())->toBe([]);
})->with([
    'success' => ['23andme.txt', UploadStatus::Complete, 3],
    'failure' => ['unrecognised.txt', UploadStatus::Failed, 0],
]);

it('polls while an upload is active and stops once it is complete', function () {
    Queue::fake();
    $this->seed(GeneVariantSeeder::class);

    $component = Livewire::actingAs($this->user)->test('genetics::profile')
        ->assertDontSeeHtml('wire:poll')
        ->set('file', uploadFixture('23andme.txt', 'genome.txt'))
        ->call('uploadResults')
        ->assertSeeHtml('wire:poll.3s')
        ->assertSee('Queued');

    expect($component->html())->toMatch('/<div[^<>]*wire:poll\.3s[^<>]*>/');

    app()->call([new ProcessGeneticUpload(GeneticUpload::sole()->id), 'handle']);

    $component->call('$refresh')
        ->assertDontSeeHtml('wire:poll')
        ->assertSee('Complete')
        ->assertSee('rs1801133');
});

it('lists the upload status, counts and error', function () {
    GeneticUpload::factory()->complete()->create([
        'user_id' => $this->user->id, 'original_name' => 'done.txt', 'found_count' => 812, 'stored_count' => 27,
    ]);
    GeneticUpload::factory()->create([
        'user_id' => $this->user->id, 'original_name' => 'bad.txt', 'status' => UploadStatus::Failed,
        'path' => null, 'error' => 'No genetic markers found in this file.',
    ]);

    $this->actingAs($this->user)->get('/?tab=genetics')
        ->assertOk()
        ->assertSee('done.txt')
        ->assertSee('812')
        ->assertSee('27')
        ->assertSee('bad.txt')
        ->assertSee('No genetic markers found in this file.')
        ->assertDontSee('wire:poll', escape: false);
});

it('shows a user only their own uploads', function () {
    $other = User::factory()->create();
    GeneticUpload::factory()->create(['user_id' => $other->id, 'original_name' => 'alice-genome.txt']);

    $this->actingAs($this->user)->get('/?tab=genetics')
        ->assertOk()
        ->assertDontSee('alice-genome.txt')
        ->assertDontSee('wire:poll', escape: false);

    $this->actingAs($other)->get('/?tab=genetics')->assertSee('alice-genome.txt');
});

it('exposes no client-settable upload id', function () {
    $component = Livewire::actingAs($this->user)->test('genetics::profile');

    expect(array_keys($component->instance()->all()))->toEqualCanonicalizing(['rsid', 'genotype', 'gene', 'group', 'search', 'file']);
});
