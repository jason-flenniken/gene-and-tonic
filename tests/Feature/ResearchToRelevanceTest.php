<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Genetics\Database\Seeders\GeneVariantSeeder;
use Modules\Intelligence\Data\SourceDocument;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Intelligence\Testing\FakeWebResearcher;
use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Supplements\Actions\ResearchSupplement;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;

it('matches a user variant to a model-written HGVS allele end to end', function () {
    $this->withoutVite();
    Storage::fake('tmp-for-tests');
    Storage::fake('genetics');
    $this->seed(GeneVariantSeeder::class);
    $user = User::factory()->create();

    // The genetics page parses the demo-style TXT into a real stored profile (rs1801133 AG).
    Livewire::actingAs($user)->test('genetics::profile')
        ->set('file', UploadedFile::fake()->createWithContent('genome.txt', file_get_contents(base_path('Modules/Genetics/tests/Fixtures/23andme.txt'))))
        ->call('upload')
        ->assertHasNoErrors();

    $supplement = Supplement::factory()->create(['name' => 'Methylfolate']);
    SupplementResearch::factory()->for($supplement)->create(['status' => ResearchStatus::Queued, 'error' => null, 'summary' => null]);

    FakeWebResearcher::install()->returning([
        new SourceDocument('S1', 'https://pubmed.ncbi.nlm.nih.gov/111/', 'MTHFR trial', 'pubmed.ncbi.nlm.nih.gov', null, 'Methylfolate and MTHFR C677T.'),
    ]);
    FakeTextGenerator::install()->respondWith([
        'summary' => 'Methylfolate is the active form of folate.',
        'safety_notes' => 'Talk to a clinician before use.',
        'effects' => [],
        'mechanisms' => [],
        'studies' => [],
        'gene_interactions' => [
            ['gene' => 'MTHFR', 'rsid' => 'rs1801133', 'allele' => 'C677T', 'relation' => GeneRelation::Helps->value, 'explanation' => 'T carriers have reduced MTHFR activity.', 'source_ids' => ['S1']],
        ],
    ]);

    app(ResearchSupplement::class)->handle($supplement->id);

    $relevance = app(GeneticRelevanceReader::class)->forUserAndSupplement($user->id, $supplement->id);

    expect($relevance->hasProfile)->toBeTrue()
        ->and($relevance->matches)->toHaveCount(1)
        ->and($relevance->matches[0]->rsid)->toBe('rs1801133')
        ->and($relevance->matches[0]->matchedOn)->toBe('allele')
        ->and($relevance->matches[0]->copies)->toBe(1);
});
