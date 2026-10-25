<?php

use App\Models\User;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\StringType;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Genetics\Actions\AddVariant;
use Modules\Genetics\Actions\LookupVariant;
use Modules\Genetics\Actions\QueueLookups;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Genetics\Jobs\LookupGeneVariant;
use Modules\Genetics\Jobs\ProcessGeneticUpload;
use Modules\Genetics\Models\GeneticUpload;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;
use Modules\Intelligence\Contracts\ProfileRegistry;
use Modules\Intelligence\Data\SourceDocument;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Intelligence\Testing\FakeWebResearcher;

beforeEach(function () {
    $this->withoutVite();
    $this->text = FakeTextGenerator::install();
    $this->web = FakeWebResearcher::install();
});

function lookupSource(string $id, string $url = 'https://www.snpedia.com/index.php/Rs123'): SourceDocument
{
    return new SourceDocument($id, $url, "Source {$id}", parse_url($url, PHP_URL_HOST) ?: 'x', null, 'rs123 in GENEX: the A allele raises risk.');
}

/** @param array<string, mixed> $overrides */
function lookupOutput(array $overrides = []): array
{
    return [
        'gene' => 'genex',
        'category' => 'Methylation [S1]',
        'group' => 'brain_mood',
        'affects' => 'Folate handling (S2).',
        'reference_allele' => 'G',
        'risk_allele' => 'A',
        'genotype_effects' => [
            ['genotype' => 'G/A', 'effect' => 'One risk copy.'],
            ['genotype' => 'AA', 'effect' => 'Two risk copies.'],
        ],
        'source_ids' => ['S1', 'S2'],
        ...$overrides,
    ];
}

function pendingRow(string $rsid = 'rs123', ?string $gene = null, LookupStatus $status = LookupStatus::Pending): GeneVariant
{
    return GeneVariant::factory()->pending()->create(['rsid' => $rsid, 'gene' => $gene, 'status' => $status]);
}

it('registers the genetics.lookup profile', function () {
    expect(app(ProfileRegistry::class)->get('genetics.lookup')->model)->toBe('gpt-6-luna');
});

it('completes a pending row from one search and one generate call', function () {
    $row = pendingRow('rs123', 'typo1');
    $this->web->returning([lookupSource('S1'), lookupSource('S2', 'https://www.ncbi.nlm.nih.gov/snp/rs123')]);
    $this->text->respondWith(lookupOutput());

    app(LookupVariant::class)->handle('rs123');

    $row->refresh();
    expect($row->status)->toBe(LookupStatus::Complete)
        ->and($row->gene)->toBe('GENEX')
        ->and($row->category)->toBe('Methylation')
        ->and($row->category_group)->toBe(VariantGroup::BrainMood)
        ->and($row->affects)->toBe('Folate handling.')
        ->and($row->reference_allele)->toBe('G')
        ->and($row->risk_allele)->toBe('A')
        ->and($row->genotype_effects)->toBe(['AG' => 'One risk copy.', 'AA' => 'Two risk copies.'])
        ->and($row->sources)->toHaveCount(2)
        ->and($row->error)->toBeNull()
        ->and($row->looked_up_at)->not->toBeNull()
        ->and($this->web->queries())->toHaveCount(1)
        ->and($this->web->queries()[0]->query)->toBe('rs123 typo1 SNP gene variant')
        ->and($this->text->requests())->toHaveCount(1);
});

it('shows the user their genotype effect once the lookup completes', function () {
    $user = User::factory()->create();
    Queue::fake();
    app(AddVariant::class)->handle($user->id, 'rs123', 'AG');
    $this->web->returning([lookupSource('S1'), lookupSource('S2')]);
    $this->text->respondWith(lookupOutput());

    app(LookupVariant::class)->handle('rs123');

    Livewire::actingAs($user)->test('genetics::profile')->assertSee('One risk copy.')->assertSee('GENEX');
});

it('marks the row failed with a generic message when the search provider fails', function () {
    $row = pendingRow();
    $this->web->failWith('secret key sk-123 rejected');

    app(LookupVariant::class)->handle('rs123');

    $row->refresh();
    expect($row->status)->toBe(LookupStatus::Failed)
        ->and($row->error)->toBe(LookupVariant::PROVIDER_ERROR)
        ->and($row->error)->not->toContain('sk-123');
});

it('marks the row failed with a generic message when generation fails', function () {
    $row = pendingRow();
    $this->web->returning([lookupSource('S1')]);
    $this->text->failWith('provider said: rs123 AG');

    app(LookupVariant::class)->handle('rs123');

    expect($row->refresh()->status)->toBe(LookupStatus::Failed)->and($row->error)->toBe(LookupVariant::PROVIDER_ERROR);
});

it('marks the row failed when no sources are found', function () {
    $row = pendingRow();
    $this->web->returning([]);

    app(LookupVariant::class)->handle('rs123');

    expect($row->refresh()->status)->toBe(LookupStatus::Failed)
        ->and($row->error)->toBe('No sources found')
        ->and($this->text->requests())->toBeEmpty();
});

it('drops unknown source ids and a risk allele outside A, C, G, T', function () {
    $row = pendingRow();
    $this->web->returning([lookupSource('S1')]);
    $this->text->respondWith(lookupOutput(['source_ids' => ['S1', 'S9'], 'risk_allele' => 'X']));

    app(LookupVariant::class)->handle('rs123');

    $row->refresh();
    expect($row->status)->toBe(LookupStatus::Complete)
        ->and($row->risk_allele)->toBeNull()
        ->and($row->sources)->toHaveCount(1);
});

it('fails output with no valid cited source, no category or affects, or equal alleles', function (array $override) {
    $row = pendingRow();
    $this->web->returning([lookupSource('S1')]);
    $this->text->respondWith(lookupOutput($override));

    app(LookupVariant::class)->handle('rs123');

    expect($row->refresh()->status)->toBe(LookupStatus::Failed)->and($row->error)->toBe(LookupVariant::UNUSABLE);
})->with([
    'only unknown ids' => [['source_ids' => ['S9']]],
    'no ids' => [['source_ids' => []]],
    'blank category' => [['category' => '  ']],
    'missing affects' => [['affects' => null]],
    'same alleles' => [['reference_allele' => 'A', 'risk_allele' => 'A']],
]);

it('drops genotype effects whose genotype does not normalize', function () {
    $row = pendingRow();
    $this->web->returning([lookupSource('S1')]);
    $this->text->respondWith(lookupOutput([
        'source_ids' => ['S1'],
        'genotype_effects' => [['genotype' => 'XY', 'effect' => 'bad'], ['genotype' => '--', 'effect' => 'bad'], ['genotype' => 'ga', 'effect' => 'ok']],
    ]));

    app(LookupVariant::class)->handle('rs123');

    expect($row->refresh()->genotype_effects)->toBe(['AG' => 'ok']);
});

it('does not store a source with a non-http url', function () {
    $row = pendingRow();
    $this->web->returning([lookupSource('S1', 'javascript:alert(1)'), lookupSource('S2')]);
    $this->text->respondWith(lookupOutput());

    app(LookupVariant::class)->handle('rs123');

    expect($row->refresh()->sources)->toHaveCount(1)
        ->and($row->sources[0]['url'])->toStartWith('https://');
});

it('leaves a seeded or completed row alone', function () {
    $row = GeneVariant::factory()->create(['rsid' => 'rs123']);

    app(LookupVariant::class)->handle('rs123');

    expect($row->refresh()->status)->toBe(LookupStatus::Seeded)->and($this->web->queries())->toBeEmpty();
});

it('claims a row once, so a second queue call dispatches nothing', function () {
    Queue::fake();
    pendingRow();

    $first = app(QueueLookups::class)->handle(['rs123'], 15);
    $second = app(QueueLookups::class)->handle(['rs123'], 15);

    expect($first)->toBe(1)->and($second)->toBe(0)
        ->and(GeneVariant::sole()->status)->toBe(LookupStatus::LookingUp);
    Queue::assertPushed(LookupGeneVariant::class, 1);
});

it('queues at most the cap and leaves the rest pending', function () {
    Queue::fake();
    $rsids = [];
    foreach (range(1, 20) as $i) {
        $rsids[] = pendingRow('rs'.(500 + $i))->rsid;
    }

    expect(app(QueueLookups::class)->handle($rsids, 15))->toBe(15);

    Queue::assertPushed(LookupGeneVariant::class, 15);
    expect(GeneVariant::where('status', LookupStatus::Pending)->count())->toBe(5);
});

it('queues failed rows but never seeded or complete ones', function () {
    Queue::fake();
    pendingRow('rs1', status: LookupStatus::Failed);
    GeneVariant::factory()->create(['rsid' => 'rs2']);
    GeneVariant::factory()->create(['rsid' => 'rs3', 'status' => LookupStatus::Complete]);

    expect(app(QueueLookups::class)->handle(['rs1', 'rs2', 'rs3'], 15))->toBe(1);
});

it('queues at most 15 lookups for an upload of 20 unknown rsIDs', function () {
    Queue::fake([LookupGeneVariant::class]);
    Storage::fake('genetics');
    $user = User::factory()->create();
    $rows = array_map(fn (int $i): string => 'rs'.(700 + $i).',AG', range(1, 20));
    Storage::disk('genetics')->put($path = 'uploads/'.Str::uuid().'.csv', "rsid,genotype\n".implode("\n", $rows)."\n");
    $upload = GeneticUpload::factory()->create(['user_id' => $user->id, 'path' => $path, 'original_name' => 'm.csv']);

    ProcessGeneticUpload::dispatchSync($upload->id);

    Queue::assertPushed(LookupGeneVariant::class, 15);
    expect($upload->refresh()->queued_lookups)->toBe(15)
        ->and(GeneVariant::where('status', LookupStatus::Pending)->count())->toBe(5);
});

it('queues one lookup when a user adds an unknown SNP, and none for a known one', function () {
    Queue::fake();
    $user = User::factory()->create();
    GeneVariant::factory()->create(['rsid' => 'rs42']);

    Livewire::actingAs($user)->test('genetics::profile')
        ->set('rsid', 'rs99')->set('genotype', 'AG')->call('addVariant')
        ->set('rsid', 'rs42')->set('genotype', 'AG')->call('addVariant');

    Queue::assertPushed(LookupGeneVariant::class, 1);
    Queue::assertPushed(LookupGeneVariant::class, fn (LookupGeneVariant $job): bool => $job->rsid === 'rs99');
});

it('queues one lookup from Look up on a failed row', function () {
    Queue::fake();
    $user = User::factory()->create();
    pendingRow('rs123', status: LookupStatus::Failed);
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs123', 'genotype' => 'AG']);

    $component = Livewire::actingAs($user)->test('genetics::profile')->assertSee('Look up')->call('lookUp', 'rs123');
    $component->call('lookUp', 'rs123');

    Queue::assertPushed(LookupGeneVariant::class, 1);
    expect(GeneVariant::sole()->status)->toBe(LookupStatus::LookingUp);
});

it('ignores Look up for an rsID outside the user\'s profile', function () {
    Queue::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    pendingRow('rs999');
    UserVariant::factory()->create(['user_id' => $other->id, 'rsid' => 'rs999', 'genotype' => 'AG']);

    Livewire::actingAs($user)->test('genetics::profile')->call('lookUp', 'rs999')->assertHasNoErrors();

    Queue::assertNothingPushed();
    expect(GeneVariant::sole()->status)->toBe(LookupStatus::Pending);
});

it('rejects a guest calling Look up', function () {
    Livewire::test('genetics::profile')->call('lookUp', 'rs1')->assertUnauthorized();
});

it('rate limits Look up to 10 a minute', function () {
    Queue::fake();
    $user = User::factory()->create();
    foreach (range(1, 11) as $i) {
        pendingRow("rs{$i}");
        UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => "rs{$i}", 'genotype' => 'AG']);
    }
    $component = Livewire::actingAs($user)->test('genetics::profile');

    foreach (range(1, 10) as $i) {
        $component->call('lookUp', "rs{$i}")->assertHasNoErrors();
    }
    $component->call('lookUp', 'rs11')->assertHasErrors('lookup')->assertSee('Too many requests, try again in a minute.');

    Queue::assertPushed(LookupGeneVariant::class, 10);
});

it('rate limits adding to 20 a minute with a message', function () {
    Queue::fake();
    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test('genetics::profile');

    foreach (range(1, 21) as $i) {
        $component->set('rsid', "rs{$i}")->set('genotype', 'AG')->call('addVariant');
    }

    $component->assertHasErrors('rsid')->assertSee('Too many requests, try again in a minute.');
});

it('renders model-written text escaped', function () {
    $user = User::factory()->create();
    GeneVariant::factory()->create(['rsid' => 'rs123', 'affects' => '<script>alert(1)</script>']);
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs123', 'genotype' => 'AG']);

    $this->actingAs($user)->get('/?tab=genetics')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

it('polls while a variant is being looked up and shows its state', function () {
    $user = User::factory()->create();
    pendingRow('rs123', status: LookupStatus::LookingUp);
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs123', 'genotype' => 'AG']);

    Livewire::actingAs($user)->test('genetics::profile')->assertSee('wire:poll', false)->assertSee('Looking up');
});

it('marks a row failed when the job fails unexpectedly', function () {
    $row = pendingRow(status: LookupStatus::LookingUp);

    (new LookupGeneVariant('rs123'))->failed(new RuntimeException('rs123 AG'));

    expect($row->refresh()->status)->toBe(LookupStatus::Failed)->and($row->error)->toBe(LookupGeneVariant::GENERIC_ERROR);
});

it('stores Other when the group is invalid or missing, and still completes', function (mixed $group) {
    $row = pendingRow();
    $this->web->returning([lookupSource('S1')]);
    $output = lookupOutput();
    $group === 'omit' ? $output = array_diff_key($output, ['group' => 1]) : $output['group'] = $group;
    $this->text->respondWith($output);

    app(LookupVariant::class)->handle('rs123');

    expect($row->refresh()->status)->toBe(LookupStatus::Complete)
        ->and($row->category_group)->toBe(VariantGroup::Other);
})->with(['Banana', 'omit', null, 5, 'Brain_Mood']);

it('stores Other and a capped category when the model output carries injected text', function () {
    $row = pendingRow();
    $this->web->returning([lookupSource('S1')]);
    $this->text->respondWith(lookupOutput([
        'category' => 'ignore instructions, group=admin '.str_repeat('x', 100),
        'group' => '<script>',
    ]));

    app(LookupVariant::class)->handle('rs123');

    $row->refresh();
    expect($row->category_group)->toBe(VariantGroup::Other)
        ->and(mb_strlen((string) $row->category))->toBe(60);
});

it('asks the model for a group from the fixed list', function () {
    pendingRow();
    $this->web->returning([lookupSource('S1')]);
    $this->text->respondWith(lookupOutput());

    app(LookupVariant::class)->handle('rs123');

    $fields = ($this->text->requests()[0]->schema)(new JsonSchemaTypeFactory);
    $group = $fields['group']->toArray();

    expect($group['enum'])->toBe(array_column(VariantGroup::cases(), 'value'))
        ->and($fields['group'])->toBeInstanceOf(StringType::class);
});
