<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Genetics\Database\Seeders\GeneVariantSeeder;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\UploadStatus;
use Modules\Genetics\Jobs\ProcessGeneticUpload;
use Modules\Genetics\Models\GeneticUpload;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;
use Modules\Genetics\Support\Extraction\MarkerExtractor;
use Modules\Genetics\Support\Parsing\ParsedVariant;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Intelligence\Testing\FakeWebResearcher;

beforeEach(function () {
    Storage::fake('genetics');
    $this->seed(GeneVariantSeeder::class);
    $this->text = FakeTextGenerator::install();
    $this->web = FakeWebResearcher::install();
    $this->user = User::factory()->create();
});

/** Stores a fixture (or raw contents) on the genetics disk and creates its queued upload row. */
function queuedUpload(User $user, string $fixtureOrContents, string $extension = 'txt', string $originalName = 'genome.txt', bool $isFixture = true): GeneticUpload
{
    $path = 'uploads/'.Str::uuid().'.'.$extension;
    $contents = $isFixture ? file_get_contents(__DIR__.'/../Fixtures/'.$fixtureOrContents) : $fixtureOrContents;
    Storage::disk('genetics')->put($path, $contents);

    return GeneticUpload::factory()->create([
        'user_id' => $user->id,
        'original_name' => $originalName,
        'path' => $path,
    ]);
}

/** @return array<string, string> rsid => genotype */
function storedGenotypes(User $user): array
{
    return UserVariant::where('user_id', $user->id)->orderBy('rsid')->get()->pluck('genotype', 'rsid')->all();
}

it('stores only reference-table SNPs from a 23andMe export with zero AI calls', function () {
    $upload = queuedUpload($this->user, '23andme.txt');
    $referenceRows = GeneVariant::count();

    ProcessGeneticUpload::dispatchSync($upload->id);

    $upload->refresh();
    expect(storedGenotypes($this->user))->toBe(['rs1801131' => 'GT', 'rs1801133' => 'AG', 'rs4680' => 'AA'])
        ->and($upload->status)->toBe(UploadStatus::Complete)
        ->and($upload->found_count)->toBe(8)
        ->and($upload->stored_count)->toBe(3)
        ->and($upload->queued_lookups)->toBe(0)
        ->and($upload->error)->toBeNull()
        ->and($upload->started_at)->not->toBeNull()
        ->and($upload->completed_at)->not->toBeNull()
        ->and(GeneVariant::count())->toBe($referenceRows)
        ->and($this->text->requests())->toBe([])
        ->and($this->web->queries())->toBe([]);

    $variant = UserVariant::where('rsid', 'rs1801133')->sole();
    expect($variant->source)->toBe('genome.txt')->and($variant->genetic_upload_id)->toBe($upload->id);
});

it('parses the seeded rows of AncestryDNA and MyHeritage exports', function (string $fixture, string $extension, array $expected, int $found) {
    $upload = queuedUpload($this->user, $fixture, $extension);
    $referenceRows = GeneVariant::count();

    ProcessGeneticUpload::dispatchSync($upload->id);

    expect(storedGenotypes($this->user))->toBe($expected)
        ->and($upload->refresh()->found_count)->toBe($found)
        ->and($upload->stored_count)->toBe(count($expected))
        ->and(GeneVariant::count())->toBe($referenceRows)
        ->and($this->text->requests())->toBe([]);
})->with([
    'AncestryDNA' => ['ancestry.txt', 'txt', ['rs1801133' => 'AG', 'rs1805087' => 'AG', 'rs601338' => 'AA'], 5],
    'MyHeritage' => ['myheritage.csv', 'csv', ['rs1801133' => 'GG', 'rs4680' => 'AG'], 4],
]);

it('stores every marker CSV row and creates pending reference rows for unknown rsIDs', function () {
    $upload = queuedUpload($this->user, 'markers.csv', 'csv', 'markers.csv');
    $referenceRows = GeneVariant::count();

    ProcessGeneticUpload::dispatchSync($upload->id);

    $pending = GeneVariant::where('status', LookupStatus::Pending)->orderBy('rsid')->pluck('gene', 'rsid')->all();

    expect(UserVariant::where('user_id', $this->user->id)->count())->toBe(6)
        ->and($upload->refresh()->stored_count)->toBe(6)
        ->and($upload->found_count)->toBe(6)
        ->and(GeneVariant::count())->toBe($referenceRows + 5)
        ->and($pending)->toBe([
            'rs90000031' => 'ABC1',
            'rs90000032' => 'ABC2',
            'rs90000033' => null,
            'rs90000034' => 'XYZ9',
            'rs90000035' => null,
        ])
        ->and($this->text->requests())->toBe([]);
});

it('lets a second upload update an existing genotype so the newest wins', function () {
    $first = queuedUpload($this->user, "rsid,genotype\nrs90000031,AG\nrs90000032,CC\n", 'csv', 'first.csv', isFixture: false);
    ProcessGeneticUpload::dispatchSync($first->id);

    $second = queuedUpload($this->user, "rsid,genotype\nrs90000031,GG\n", 'csv', 'second.csv', isFixture: false);
    ProcessGeneticUpload::dispatchSync($second->id);

    $updated = UserVariant::where('rsid', 'rs90000031')->sole();

    expect(storedGenotypes($this->user))->toBe(['rs90000031' => 'GG', 'rs90000032' => 'CC'])
        ->and($updated->source)->toBe('second.csv')
        ->and($updated->genetic_upload_id)->toBe($second->id);
});

it('moves through parsing and looking up to complete', function () {
    $upload = queuedUpload($this->user, '23andme.txt');
    $seen = [];
    GeneticUpload::updated(function (GeneticUpload $changed) use (&$seen): void {
        if ($changed->wasChanged('status')) {
            $seen[] = $changed->status;
        }
    });

    ProcessGeneticUpload::dispatchSync($upload->id);

    expect($seen)->toBe([UploadStatus::Parsing, UploadStatus::LookingUp, UploadStatus::Complete]);
});

it('checks raw-export rsIDs against the reference table in chunks of 1000', function () {
    $rows = ["rs1801133\t1\t1\tAG"];
    for ($i = 1; $i <= 2400; $i++) {
        $rows[] = 'rs'.(80000000 + $i)."\t1\t".$i."\tCT";
    }
    $rows[] = "rs4680\t22\t1\tAG";
    $upload = queuedUpload($this->user, "# rsid\tchromosome\tposition\tgenotype\n".implode("\n", $rows)."\n", isFixture: false);

    $lookups = 0;
    DB::listen(function ($query) use (&$lookups): void {
        if (str_contains($query->sql, 'from "gene_variants" where "rsid" in')) {
            $lookups++;
        }
    });

    ProcessGeneticUpload::dispatchSync($upload->id);

    expect($lookups)->toBe(3)
        ->and(storedGenotypes($this->user))->toBe(['rs1801133' => 'AG', 'rs4680' => 'AG'])
        ->and($upload->refresh()->found_count)->toBe(2402);
});

it('fails an unrecognised file with no markers and deletes it', function () {
    $upload = queuedUpload($this->user, 'unrecognised.txt');
    $path = $upload->path;

    ProcessGeneticUpload::dispatchSync($upload->id);

    $upload->refresh();
    expect($upload->status)->toBe(UploadStatus::Failed)
        ->and($upload->error)->toBe('No genetic markers found in this file.')
        ->and($upload->completed_at)->not->toBeNull()
        ->and($upload->path)->toBeNull()
        ->and(Storage::disk('genetics')->exists($path))->toBeFalse()
        ->and(UserVariant::count())->toBe(0);
});

it('fails a known format with no valid rows', function () {
    $upload = queuedUpload($this->user, "# rsid\tchromosome\tposition\tgenotype\nrs1801133\t1\t1\t--\n", isFixture: false);

    ProcessGeneticUpload::dispatchSync($upload->id);

    expect($upload->refresh()->status)->toBe(UploadStatus::Failed)
        ->and($upload->error)->toBe('No genetic markers found in this file.');
});

it('sends a pdf to the marker extractor and stores every extracted row', function () {
    app()->instance(MarkerExtractor::class, new class implements MarkerExtractor
    {
        public function extract(string $absolutePath, string $extension): array
        {
            return [new ParsedVariant('rs90000041', 'AG', 'ABC1')];
        }
    });
    $upload = queuedUpload($this->user, '23andme.txt', 'pdf', 'report.pdf');

    ProcessGeneticUpload::dispatchSync($upload->id);

    expect(storedGenotypes($this->user))->toBe(['rs90000041' => 'AG'])
        ->and($upload->refresh()->status)->toBe(UploadStatus::Complete);
});

it('leaves the genetics disk empty after success and after failure', function (string $fixture) {
    $upload = queuedUpload($this->user, $fixture);

    ProcessGeneticUpload::dispatchSync($upload->id);

    expect(Storage::disk('genetics')->allFiles())->toBe([])
        ->and($upload->refresh()->path)->toBeNull();
})->with(['23andme.txt', 'unrecognised.txt']);

it('marks the upload failed with a generic message and deletes the file when the last attempt throws', function () {
    app()->instance(MarkerExtractor::class, new class implements MarkerExtractor
    {
        public function extract(string $absolutePath, string $extension): array
        {
            throw new RuntimeException('provider detail that must not reach the UI');
        }
    });
    $upload = queuedUpload($this->user, '23andme.txt', 'pdf');

    ProcessGeneticUpload::dispatchSync($upload->id);

    $upload->refresh();
    expect($upload->status)->toBe(UploadStatus::Failed)
        ->and($upload->error)->toBe('Processing failed unexpectedly. Try uploading the file again.')
        ->and($upload->path)->toBeNull()
        ->and(Storage::disk('genetics')->allFiles())->toBe([]);
});

it('keeps the file for a retry when an earlier attempt throws', function () {
    app()->instance(MarkerExtractor::class, new class implements MarkerExtractor
    {
        public function extract(string $absolutePath, string $extension): array
        {
            throw new RuntimeException('transient');
        }
    });
    $upload = queuedUpload($this->user, '23andme.txt', 'pdf');

    $job = (new ProcessGeneticUpload($upload->id))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertReleased();
    expect(Storage::disk('genetics')->exists((string) $upload->refresh()->path))->toBeTrue()
        ->and($upload->status)->toBe(UploadStatus::Parsing);
});

it('writes a generic error and deletes the file in failed()', function () {
    $upload = queuedUpload($this->user, '23andme.txt');

    (new ProcessGeneticUpload($upload->id))->failed(new RuntimeException('worker timed out'));

    $upload->refresh();
    expect($upload->status)->toBe(UploadStatus::Failed)
        ->and($upload->error)->toBe('Processing failed unexpectedly. Try uploading the file again.')
        ->and($upload->path)->toBeNull()
        ->and(Storage::disk('genetics')->allFiles())->toBe([]);
});

it('does nothing for an upload that already finished', function () {
    $upload = GeneticUpload::factory()->complete()->create(['user_id' => $this->user->id, 'found_count' => 3]);

    ProcessGeneticUpload::dispatchSync($upload->id);

    expect($upload->refresh()->status)->toBe(UploadStatus::Complete)
        ->and($upload->found_count)->toBe(3);
});
