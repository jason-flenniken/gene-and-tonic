<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Genetics\Enums\UploadStatus;
use Modules\Genetics\Jobs\LookupGeneVariant;
use Modules\Genetics\Jobs\ProcessGeneticUpload;
use Modules\Genetics\Models\GeneticUpload;
use Modules\Genetics\Models\UserVariant;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Intelligence\Testing\FakeWebResearcher;

beforeEach(function () {
    Queue::fake([LookupGeneVariant::class]);
    config(['genetics.lookup_cap' => 0]);
    Storage::fake('genetics');
    $this->text = FakeTextGenerator::install();
    FakeWebResearcher::install();
    $this->user = User::factory()->create();
});

it('does not name the upload action after a Livewire JavaScript helper', function () {
    $this->withoutVite()->actingAs($this->user)->get('/?tab=genetics')
        ->assertOk()
        ->assertSee('Upload results')
        ->assertDontSee('wire:submit="upload"', false);
});

it('reads a complex marker CSV locally, with loose headers and messy genotype cells', function () {
    $path = 'uploads/'.Str::uuid().'.csv';
    Storage::disk('genetics')->put($path, file_get_contents(__DIR__.'/../Fixtures/complex-markers.csv'));
    $upload = GeneticUpload::factory()->create(['user_id' => $this->user->id, 'original_name' => 'markers.csv', 'path' => $path]);

    // The one rsID row local parsing can't read (a star allele) may go to the AI as a leftover.
    $this->text->respondWith(['variants' => []]);

    ProcessGeneticUpload::dispatchSync($upload->id);

    $stored = UserVariant::where('user_id', $this->user->id)->orderBy('rsid')->get()->pluck('genotype', 'rsid')->all();

    expect($upload->refresh()->status)->toBe(UploadStatus::Complete)
        ->and($stored)->toBe([
            'rs1801131' => 'TT',
            'rs1801133' => 'AG',
            'rs1801394' => 'AG',
            'rs1805087' => 'AG',
            'rs4680' => 'AA',
            'rs601338' => 'AG',
        ])
        ->and(UserVariant::where('user_id', $this->user->id)->where('rsid', 'rs1805087')->value('gene'))->toBe('MTR');
});
