<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Genetics\Enums\ParseMethod;
use Modules\Genetics\Enums\UploadStatus;
use Modules\Genetics\Jobs\LookupGeneVariant;
use Modules\Genetics\Jobs\ProcessGeneticUpload;
use Modules\Genetics\Models\GeneticUpload;
use Modules\Genetics\Models\UserVariant;
use Modules\Intelligence\Data\PromptRequest;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Intelligence\Testing\FakeWebResearcher;

const MARKER_HEADER = 'Category,Category Context,Marker,rsID,Gene / Locus,Variant / Marker Name,Genotype / Result,Interpretation / Notes,Raw Source Entry';

beforeEach(function () {
    Queue::fake([LookupGeneVariant::class]);
    config(['genetics.lookup_cap' => 0]);
    Storage::fake('genetics');
    $this->text = FakeTextGenerator::install();
    FakeWebResearcher::install();
    $this->user = User::factory()->create();
});

/** One row in MARKER_HEADER's layout; every free-text cell carries a sentinel that must never reach the model. */
function markerRow(string $marker, string $rsid, string $gene, string $genotype): string
{
    return implode(',', [
        'METHYLATION', '"SENTINEL-CONTEXT, folate"', $marker, $rsid, $gene, 'V1', '"'.$genotype.'"',
        '"SENTINEL-NOTES: reduced activity"', '"SENTINEL-RAW '.$marker.'"',
    ]);
}

function markerUpload(User $user, string $contents, string $extension = 'csv'): GeneticUpload
{
    $path = 'uploads/'.Str::uuid().'.'.$extension;
    Storage::disk('genetics')->put($path, $contents);

    return GeneticUpload::factory()->create(['user_id' => $user->id, 'original_name' => 'markers.'.$extension, 'path' => $path]);
}

/** @return array<string, string> */
function markerGenotypes(User $user): array
{
    return UserVariant::where('user_id', $user->id)->orderBy('rsid')->get()->pluck('genotype', 'rsid')->all();
}

/** Eight rows that read locally, one with an rsID that doesn't, and one without an rsID. */
function mostlyReadableCsv(): string
{
    return implode("\n", [
        MARKER_HEADER,
        markerRow('MTHFR C677T', 'rs1801133', 'MTHFR', 'A/G'),
        markerRow('MTHFR A1298C', 'rs1801131', 'MTHFR', 'T/T'),
        markerRow('COMT V158M', 'rs4680', 'COMT', 'A/A'),
        markerRow('MTR A2756G', 'rs1805087', 'MTR', 'GRCh38 genomic A/G; protein Asp919Gly'),
        markerRow('MTRR A66G', 'rs1801394', 'MTRR', 'A/G (as reported)'),
        markerRow('FUT2', 'rs601338', 'FUT2', 'G/A'),
        markerRow('ABC1', 'rs90000001', 'ABC1', 'C/C'),
        markerRow('ABC2', 'rs90000002', 'ABC2', 'T/C'),
        markerRow('CYP2C19*2', 'rs4244285', 'CYP2C19', 'heterozygous A and G'),
        markerRow('APOE', '', 'APOE', 'E3/E4'),
    ])."\n";
}

/** Two rows that read locally and three with an rsID that don't. */
function mostlyUnreadableCsv(): string
{
    return implode("\n", [
        MARKER_HEADER,
        markerRow('MTHFR C677T', 'rs1801133', 'MTHFR', 'A/G'),
        markerRow('COMT V158M', 'rs4680', 'COMT', 'A/A'),
        markerRow('MTR', 'rs1805087', 'MTR', 'one A and one G'),
        markerRow('MTRR', 'rs1801394', 'MTRR', 'both G'),
        markerRow('FUT2', 'rs601338', 'FUT2', 'non-secretor'),
    ])."\n";
}

it('keeps local rows and sends only the unread rsID row in one call above the threshold', function () {
    $this->text->respondWith(['variants' => [
        ['rsid' => 'rs4244285', 'gene' => 'CYP2C19', 'genotype' => 'A/G'],
        ['rsid' => 'rs1801133', 'gene' => 'MTHFR', 'genotype' => 'CC'],
        ['rsid' => 'rs99999999', 'gene' => 'FAKE1', 'genotype' => 'AA'],
    ]]);
    $upload = markerUpload($this->user, mostlyReadableCsv());

    ProcessGeneticUpload::dispatchSync($upload->id);

    $requests = $this->text->requests();
    expect($requests)->toHaveCount(1)
        ->and($requests[0]->profile)->toBe('genetics.extract')
        ->and($requests[0]->variables['lines'])->toBe([
            'Marker: CYP2C19*2 | rsID: rs4244285 | Gene / Locus: CYP2C19 | Variant / Marker Name: V1 | Genotype / Result: heterozygous A and G',
        ]);

    expect(markerGenotypes($this->user))->toBe([
        'rs1801131' => 'TT',
        'rs1801133' => 'AG',
        'rs1801394' => 'AG',
        'rs1805087' => 'AG',
        'rs4244285' => 'AG',
        'rs4680' => 'AA',
        'rs601338' => 'AG',
        'rs90000001' => 'CC',
        'rs90000002' => 'CT',
    ]);

    $upload->refresh();
    expect($upload->status)->toBe(UploadStatus::Complete)
        ->and($upload->parse_method)->toBe(ParseMethod::LocalAi)
        ->and($upload->local_count)->toBe(8)
        ->and($upload->ai_count)->toBe(1)
        ->and($upload->skipped_count)->toBe(1)
        ->and($upload->found_count)->toBe(9)
        ->and($upload->stored_count)->toBe(9);
});

it('never sends category, notes or raw-source cells to the model', function (string $contents) {
    $this->text->respondWith(['variants' => []]);

    ProcessGeneticUpload::dispatchSync(markerUpload($this->user, $contents)->id);

    $requests = $this->text->requests();
    expect($requests)->not->toBeEmpty();

    foreach ($requests as $request) {
        $prompt = view('genetics::prompts.extract', $request->variables)->render();

        expect(json_encode($request->variables))->not->toContain('SENTINEL')->not->toContain('METHYLATION')
            ->and($prompt)->not->toContain('SENTINEL')->not->toContain('Interpretation');
    }
})->with([
    'leftover rows' => [mostlyReadableCsv()],
    'every rsID row' => [mostlyUnreadableCsv()],
]);

it('discards the local rows and sends every rsID row below the threshold', function () {
    $this->text->respondWith(['variants' => [
        ['rsid' => 'rs1801133', 'gene' => 'MTHFR', 'genotype' => 'GG'],
        ['rsid' => 'rs1805087', 'gene' => 'MTR', 'genotype' => 'A/G'],
        ['rsid' => 'rs1801394', 'gene' => 'MTRR', 'genotype' => 'GG'],
    ]]);
    $upload = markerUpload($this->user, mostlyUnreadableCsv());

    ProcessGeneticUpload::dispatchSync($upload->id);

    $lines = $this->text->requests()[0]->variables['lines'];
    expect($this->text->requests())->toHaveCount(1)
        ->and($lines)->toHaveCount(5)
        ->and($lines[0])->toBe('Marker: MTHFR C677T | rsID: rs1801133 | Gene / Locus: MTHFR | Variant / Marker Name: V1 | Genotype / Result: A/G')
        ->and(markerGenotypes($this->user))->toBe(['rs1801133' => 'GG', 'rs1801394' => 'GG', 'rs1805087' => 'AG']);

    $upload->refresh();
    expect($upload->parse_method)->toBe(ParseMethod::Ai)
        ->and($upload->local_count)->toBe(0)
        ->and($upload->ai_count)->toBe(3)
        ->and($upload->skipped_count)->toBe(2)
        ->and($upload->stored_count)->toBe(3);
});

it('reads the threshold from config', function () {
    config(['genetics.local_min_ratio' => 0.4]);
    $this->text->respondWith(['variants' => []]);
    $upload = markerUpload($this->user, mostlyUnreadableCsv());

    ProcessGeneticUpload::dispatchSync($upload->id);

    expect($this->text->requests()[0]->variables['lines'])->toHaveCount(3)
        ->and(markerGenotypes($this->user))->toBe(['rs1801133' => 'AG', 'rs4680' => 'AA'])
        ->and($upload->refresh()->parse_method)->toBe(ParseMethod::LocalAi);
});

it('makes no model call when every rsID row reads locally', function () {
    $upload = markerUpload($this->user, (string) file_get_contents(__DIR__.'/../Fixtures/markers.csv'));

    ProcessGeneticUpload::dispatchSync($upload->id);

    $upload->refresh();
    expect($this->text->requests())->toBe([])
        ->and($upload->status)->toBe(UploadStatus::Complete)
        ->and($upload->parse_method)->toBe(ParseMethod::Local)
        ->and($upload->local_count)->toBe(6)
        ->and($upload->ai_count)->toBe(0)
        ->and($upload->skipped_count)->toBe(2);
});

it('keeps the local rows when the leftover call fails', function () {
    $this->text->failWith('provider said: rs4244285 AG');
    $upload = markerUpload($this->user, mostlyReadableCsv());

    ProcessGeneticUpload::dispatchSync($upload->id);

    $upload->refresh();
    expect($upload->status)->toBe(UploadStatus::Complete)
        ->and($upload->error)->toBeNull()
        ->and(markerGenotypes($this->user))->toHaveCount(8)->not->toHaveKey('rs4244285')
        ->and($upload->local_count)->toBe(8)
        ->and($upload->ai_count)->toBe(0)
        ->and($upload->skipped_count)->toBe(2);
});

it('fails an over-cap marker list before any model call', function () {
    config(['genetics.max_marker_rows' => 5]);
    $upload = markerUpload($this->user, mostlyReadableCsv());

    ProcessGeneticUpload::dispatchSync($upload->id);

    expect($this->text->requests())->toBe([])
        ->and($upload->refresh()->error)->toBe(ProcessGeneticUpload::TOO_MANY_MARKERS)
        ->and(UserVariant::count())->toBe(0);
});

/** Ten marker lines in a CSV with no header any parser recognises. */
function unrecognisedMarkerText(): string
{
    return implode("\n", array_map(fn (int $i): string => 'Sample rs'.(90000100 + $i).' genotype AG and some filler', range(1, 10)))."\n";
}

it('splits a long headerless CSV into several calls of at most max_chars, dropping nothing', function () {
    config(['genetics.extract.max_chars' => 100, 'genetics.extract.max_chunks' => 10]);
    $all = array_map(fn (int $i): array => ['rsid' => 'rs'.(90000100 + $i), 'gene' => null, 'genotype' => 'AG'], range(1, 10));
    foreach (range(1, 10) as $ignored) {
        $this->text->respondWith(['variants' => $all]);
    }
    $upload = markerUpload($this->user, unrecognisedMarkerText());

    ProcessGeneticUpload::dispatchSync($upload->id);

    $requests = $this->text->requests();
    $sent = array_merge(...array_map(fn (PromptRequest $request): array => $request->variables['lines'], $requests));

    expect(count($requests))->toBeGreaterThanOrEqual(2)
        ->and($sent)->toBe(array_values(array_filter(explode("\n", unrecognisedMarkerText()))));

    foreach ($requests as $request) {
        expect(mb_strlen(implode("\n", $request->variables['lines'])))->toBeLessThanOrEqual(100);
    }

    $upload->refresh();
    expect($upload->parse_method)->toBe(ParseMethod::Ai)
        ->and($upload->ai_count)->toBe(10)
        ->and(UserVariant::count())->toBe(10);
});

it('stops at max_chunks and logs the truncation without any marker text', function () {
    config(['genetics.extract.max_chars' => 100, 'genetics.extract.max_chunks' => 2]);
    Log::spy();
    $this->text->respondWith(['variants' => []])->respondWith(['variants' => []]);

    ProcessGeneticUpload::dispatchSync(markerUpload($this->user, unrecognisedMarkerText())->id);

    expect($this->text->requests())->toHaveCount(2);
    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context): bool => str_contains($message, 'chunk cap')
            && $context['lines'] === 10 && $context['sent'] < 10
            && ! str_contains((string) json_encode($context), 'rs9000'),
    )->once();
});

it('shows how the rows were read on the upload row', function () {
    GeneticUpload::factory()->complete()->create([
        'user_id' => $this->user->id, 'original_name' => 'mixed.csv', 'found_count' => 74, 'stored_count' => 74,
        'parse_method' => ParseMethod::LocalAi, 'local_count' => 70, 'ai_count' => 4, 'skipped_count' => 9,
    ]);
    GeneticUpload::factory()->complete()->create(['user_id' => $this->user->id, 'original_name' => 'older.csv']);

    Livewire::actingAs($this->user)->test('genetics::profile')
        ->assertSee('70 read, 4 via AI, 9 skipped')
        ->assertSee('older.csv');
});

it('records marker lines past max_chunks as skipped on the whole-file AI path', function () {
    config(['genetics.extract.max_chars' => 100, 'genetics.extract.max_chunks' => 2]);
    $this->text->respondWith(['variants' => []])->respondWith(['variants' => []]);
    $upload = markerUpload($this->user, unrecognisedMarkerText(), 'txt');

    ProcessGeneticUpload::dispatchSync($upload->id);

    $sentCount = array_sum(array_map(fn (PromptRequest $request): int => count($request->variables['lines']), $this->text->requests()));
    $unsent = 10 - $sentCount;

    expect($this->text->requests())->toHaveCount(2)
        ->and($unsent)->toBeGreaterThan(0)
        ->and($upload->refresh()->parse_method)->toBe(ParseMethod::Ai)
        ->and($upload->skipped_count)->toBe($unsent)
        ->and($upload->readSummary())->toContain($unsent.' skipped');
});
