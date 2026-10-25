<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Genetics\Console\DemoFilesCommand;
use Modules\Genetics\Database\Seeders\GeneVariantSeeder;
use Modules\Genetics\Enums\UploadStatus;
use Modules\Genetics\Jobs\LookupGeneVariant;
use Modules\Genetics\Jobs\ProcessGeneticUpload;
use Modules\Genetics\Models\GeneticUpload;
use Modules\Genetics\Models\UserVariant;
use Modules\Genetics\Support\Extraction\LocalTextExtractor;
use Modules\Intelligence\Testing\FakeTextGenerator;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('tmp-for-tests');
    Storage::fake('genetics');
    Queue::fake([LookupGeneVariant::class]);
    $this->seed(GeneVariantSeeder::class);
    $this->text = FakeTextGenerator::install();
    $this->user = User::factory()->create();

    $this->artisan('genetics:demo-files')->assertSuccessful();
    $this->demoPath = fn (string $file): string => storage_path('app/demo/'.$file);
});

function uploadDemoFile(string $path, string $name): GeneticUpload
{
    $user = test()->user;

    Livewire::actingAs($user)->test('genetics::profile')
        ->set('file', UploadedFile::fake()->createWithContent($name, (string) file_get_contents($path)))
        ->call('uploadResults')
        ->assertHasNoErrors();

    $upload = GeneticUpload::sole();
    ProcessGeneticUpload::dispatchSync($upload->id);

    return $upload->refresh();
}

it('writes a roughly 40-row TXT with the seeded methylfolate risk genotypes', function () {
    $contents = (string) file_get_contents(($this->demoPath)(DemoFilesCommand::TXT_FILE));
    $rows = array_filter(explode("\n", $contents), fn (string $line): bool => $line !== '' && $line[0] !== '#');

    expect($rows)->toHaveCount(40)
        ->and($contents)->toContain("rs1801133\t1\t11796321\tAG")
        ->and($contents)->toContain("rs1801131\t1\t11794419\tGT")
        ->and($contents)->toContain('rs1805087')->toContain('rs1801394')->toContain('rs601338');
});

it('writes a PDF the local extractor can read', function () {
    $text = (new LocalTextExtractor)->extract(($this->demoPath)(DemoFilesCommand::PDF_FILE), 'pdf');

    expect(file_get_contents(($this->demoPath)(DemoFilesCommand::PDF_FILE), length: 8))->toStartWith('%PDF-1.4')
        ->and($text)->toContain('Patient: Jane Fakename')
        ->and($text)->toContain('rs1801133')->toContain('rs90000101')->toContain('rs90000102');
});

it('processes the demo TXT through the upload page to Complete with stored variants', function () {
    $upload = uploadDemoFile(($this->demoPath)(DemoFilesCommand::TXT_FILE), 'sample-23andme.txt');

    expect($upload->status)->toBe(UploadStatus::Complete)
        ->and(UserVariant::where('user_id', $this->user->id)->count())->toBeGreaterThanOrEqual(5);
});

it('processes the demo PDF through the upload page to Complete with stored variants', function () {
    $this->text->respondWith(['variants' => [
        ['rsid' => 'rs1801133', 'gene' => 'MTHFR', 'genotype' => 'AG'],
        ['rsid' => 'rs1805087', 'gene' => 'MTR', 'genotype' => 'AG'],
        ['rsid' => 'rs90000101', 'gene' => 'ABCD1', 'genotype' => 'CT'],
        ['rsid' => 'rs90000102', 'gene' => 'XYZ1', 'genotype' => 'GG'],
    ]]);

    $upload = uploadDemoFile(($this->demoPath)(DemoFilesCommand::PDF_FILE), 'sample-report.pdf');

    expect($upload->status)->toBe(UploadStatus::Complete)
        ->and(UserVariant::where('user_id', $this->user->id)->count())->toBeGreaterThanOrEqual(1);
});
