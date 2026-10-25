<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Supplements\Actions\AddSupplement;
use Modules\Supplements\Actions\StartResearch;
use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Jobs\RunSupplementResearch;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementEffect;
use Modules\Supplements\Models\SupplementMechanism;
use Modules\Supplements\Models\SupplementResearch;
use Modules\Supplements\Models\SupplementSource;
use Modules\Supplements\Models\SupplementStudy;
use Modules\Supplements\Repositories\ResearchRepository;
use Modules\Supplements\Repositories\SupplementRepository;

beforeEach(function () {
    $this->withoutVite();
    Queue::fake();
});

it('binds the repositories', function () {
    expect(app(SupplementRepository::class))->toBeInstanceOf(SupplementRepository::class)
        ->and(app(ResearchRepository::class))->toBeInstanceOf(ResearchRepository::class);
});

it('adds a supplement as queued and pushes exactly one job', function () {
    Livewire::test('supplements::catalog')
        ->set('name', '  Magnesium   glycinate ')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('name', '');

    $supplement = Supplement::sole();
    expect($supplement->name)->toBe('Magnesium glycinate')
        ->and($supplement->slug)->toBe('magnesium-glycinate')
        ->and($supplement->research->status)->toBe(ResearchStatus::Queued);
    Queue::assertPushed(RunSupplementResearch::class, 1);
});

it('renders the catalog on its page with rows and links', function () {
    $supplement = Supplement::factory()->create(['name' => 'Zinc']);
    $research = SupplementResearch::factory()->for($supplement)->create(['status' => ResearchStatus::Complete]);
    SupplementEffect::factory()->count(2)->for($research, 'research')->create();
    SupplementStudy::factory()->for($research, 'research')->create();

    $this->get('/supplements')
        ->assertOk()
        ->assertSee('Zinc')
        ->assertSee('Complete')
        ->assertSee('/supplements/'.$supplement->id);

    Livewire::test('supplements::catalog')->assertSeeInOrder(['Zinc', '2 effects', '1 study', '0 sources']);
});

it('rejects invalid names', function (string $name) {
    Livewire::test('supplements::catalog')
        ->set('name', $name)
        ->call('add')
        ->assertHasErrors(['name']);

    expect(Supplement::count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'empty' => '',
    'too short' => 'a',
    'too long' => str_repeat('a', 101),
    'disallowed char' => 'Mag$nesium',
    'script tag' => '<script>alert(1)</script>',
]);

it('rejects a case-insensitive duplicate', function () {
    $component = Livewire::test('supplements::catalog')->set('name', 'Magnesium glycinate')->call('add');
    $component->set('name', 'magnesium GLYCINATE')->call('add')->assertHasErrors(['name']);

    expect(Supplement::count())->toBe(1);
    Queue::assertPushed(RunSupplementResearch::class, 1);
});

it('turns a unique constraint race into a validation error', function () {
    $repo = new class(app(SupplementRepository::class)) implements SupplementRepository
    {
        public function __construct(private SupplementRepository $inner) {}

        public function create(string $name): Supplement
        {
            return $this->inner->create($name);
        }

        public function find(int $id): ?Supplement
        {
            return $this->inner->find($id);
        }

        public function existsByName(string $name): bool
        {
            return false; // simulates the lost race: the pre-check passes
        }

        public function delete(Supplement $supplement): void
        {
            $this->inner->delete($supplement);
        }

        public function paginateWithResearch(int $perPage, string $status = 'all'): LengthAwarePaginator
        {
            return $this->inner->paginateWithResearch($perPage, $status);
        }

        public function countByStatus(): array
        {
            return $this->inner->countByStatus();
        }

        public function total(): int
        {
            return $this->inner->total();
        }

        public function recentlyCompleted(int $limit): Collection
        {
            return $this->inner->recentlyCompleted($limit);
        }
    };

    $action = new AddSupplement($repo, app(ResearchRepository::class));
    $action->handle('Magnesium');

    expect(fn () => $action->handle('MAGNESIUM'))->toThrow(ValidationException::class);
    expect(Supplement::count())->toBe(1);
    Queue::assertPushed(RunSupplementResearch::class, 1);
});

it('gives colliding slugs a suffix', function () {
    $action = app(AddSupplement::class);
    $action->handle('Vitamin-D');
    $action->handle('Vitamin D');

    expect(Supplement::orderBy('id')->pluck('slug')->all())->toBe(['vitamin-d', 'vitamin-d-2']);
});

it('does not start research while a run is active', function (ResearchStatus $status, bool $started) {
    $supplement = Supplement::factory()->create();
    SupplementResearch::factory()->for($supplement)->create(['status' => $status]);

    expect(app(StartResearch::class)->handle($supplement))->toBe($started);

    $started ? Queue::assertPushed(RunSupplementResearch::class, 1) : Queue::assertNothingPushed();
})->with([
    [ResearchStatus::Searching, false],
    [ResearchStatus::Analyzing, false],
    [ResearchStatus::Queued, false],
    [ResearchStatus::Failed, true],
    [ResearchStatus::Complete, true],
]);

it('dispatches one job when a second start follows the first claim', function () {
    $supplement = Supplement::factory()->create();
    SupplementResearch::factory()->for($supplement)->create(['status' => ResearchStatus::Complete]);

    $action = app(StartResearch::class);

    expect($action->handle($supplement))->toBeTrue()
        ->and($action->handle($supplement))->toBeFalse();
    Queue::assertPushed(RunSupplementResearch::class, 1);
});

it('dispatches nothing when a concurrent request loses the claim', function () {
    $supplement = Supplement::factory()->create();
    SupplementResearch::factory()->for($supplement)->create(['status' => ResearchStatus::Failed]);

    $winner = new StartResearch(app(ResearchRepository::class));
    $loser = new StartResearch(app(ResearchRepository::class));

    expect($winner->handle($supplement))->toBeTrue()
        ->and($loser->handle($supplement))->toBeFalse();
    Queue::assertPushed(RunSupplementResearch::class, 1);
});

it('claims a run for a supplement without a research row exactly once', function () {
    $supplement = Supplement::factory()->create();
    $repo = app(ResearchRepository::class);

    expect($repo->claimRun($supplement))->toBeTrue()
        ->and($repo->claimRun($supplement))->toBeFalse()
        ->and(SupplementResearch::where('supplement_id', $supplement->id)->count())->toBe(1)
        ->and($supplement->fresh()->research->status)->toBe(ResearchStatus::Queued);
});

it('deletes after confirm and cascades to research and findings', function () {
    $supplement = Supplement::factory()->create();
    $research = SupplementResearch::factory()->for($supplement)->create();
    SupplementSource::factory()->for($research, 'research')->create();
    SupplementEffect::factory()->for($research, 'research')->create();
    SupplementMechanism::factory()->for($research, 'research')->create();
    SupplementStudy::factory()->for($research, 'research')->create();
    $other = Supplement::factory()->create();

    Livewire::test('supplements::catalog')
        ->call('confirmDelete', $supplement->id)
        ->assertSet('deleteId', $supplement->id)
        ->call('delete')
        ->assertSet('deleteId', null);

    expect(Supplement::pluck('id')->all())->toBe([$other->id])
        ->and(SupplementResearch::count())->toBe(0)
        ->and(SupplementSource::count())->toBe(0)
        ->and(SupplementEffect::count())->toBe(0)
        ->and(SupplementMechanism::count())->toBe(0)
        ->and(SupplementStudy::count())->toBe(0);
});

it('reports counts by status and recently completed', function () {
    $a = Supplement::factory()->create();
    SupplementResearch::factory()->for($a)->create(['status' => ResearchStatus::Complete, 'completed_at' => now()]);
    SupplementResearch::factory()->for(Supplement::factory())->create(['status' => ResearchStatus::Failed]);

    $repo = app(SupplementRepository::class);
    expect($repo->total())->toBe(2)
        ->and($repo->countByStatus())->toBe(['complete' => 1, 'failed' => 1])
        ->and($repo->recentlyCompleted(5)->pluck('id')->all())->toBe([$a->id]);
});

it('polls only while some run is queued, searching or analyzing', function (ResearchStatus $status, bool $polls) {
    SupplementResearch::factory()->for(Supplement::factory())->create(['status' => $status]);

    $component = Livewire::test('supplements::catalog');

    $polls ? $component->assertSeeHtml('wire:poll.3s') : $component->assertDontSeeHtml('wire:poll');
})->with([
    [ResearchStatus::Queued, true],
    [ResearchStatus::Searching, true],
    [ResearchStatus::Analyzing, true],
    [ResearchStatus::Complete, false],
    [ResearchStatus::Failed, false],
]);

it('shows the error and a Retry button on failed rows, and retry queues exactly one job', function () {
    $supplement = Supplement::factory()->create(['name' => 'Zinc']);
    SupplementResearch::factory()->for($supplement)->create(['status' => ResearchStatus::Failed, 'error' => 'No trusted sources found for Zinc <b>']);

    $component = Livewire::test('supplements::catalog')
        ->assertSee('No trusted sources found for Zinc')
        ->assertSee('Retry')
        ->assertDontSeeHtml('<b>');

    $component->call('retry', $supplement->id);
    Queue::assertPushed(RunSupplementResearch::class, 1);
    expect($supplement->fresh()->research->status)->toBe(ResearchStatus::Queued);

    $component->call('retry', $supplement->id);
    Queue::assertPushed(RunSupplementResearch::class, 1);
});

it('does not queue a retry while the run is queued', function () {
    $supplement = Supplement::factory()->create();
    SupplementResearch::factory()->for($supplement)->create(['status' => ResearchStatus::Queued]);

    Livewire::test('supplements::catalog')->call('retry', $supplement->id);

    Queue::assertNothingPushed();
});

it('shows a Re-run button on completed rows', function () {
    $supplement = Supplement::factory()->create();
    SupplementResearch::factory()->for($supplement)->create(['status' => ResearchStatus::Complete]);

    Livewire::test('supplements::catalog')->assertSee('Re-run')->call('retry', $supplement->id);

    Queue::assertPushed(RunSupplementResearch::class, 1);
});

it('filters the catalog by status and counts sources', function () {
    $make = function (string $name, ResearchStatus $status, int $sources = 0) {
        $supplement = Supplement::factory()->create(['name' => $name]);
        $research = SupplementResearch::factory()->create(['supplement_id' => $supplement->id, 'status' => $status]);
        SupplementSource::factory()->count($sources)->create(['research_id' => $research->id]);
    };
    $make('A', ResearchStatus::Complete, 3);
    $make('B', ResearchStatus::Queued);
    $make('C', ResearchStatus::Analyzing);
    $make('D', ResearchStatus::Failed);

    $repo = app(SupplementRepository::class);
    $names = fn (string $status) => $repo->paginateWithResearch(10, $status)->pluck('name')->all();

    expect($names('all'))->toBe(['A', 'B', 'C', 'D'])
        ->and($names('complete'))->toBe(['A'])
        ->and($names('active'))->toBe(['B', 'C'])
        ->and($names('failed'))->toBe(['D'])
        ->and($names('nonsense'))->toBe(['A', 'B', 'C', 'D'])
        ->and($repo->paginateWithResearch(10)->first()->sources_count)->toBe(3);
});

function catalogRow(string $name, ResearchStatus $status, array $research = []): SupplementResearch
{
    return SupplementResearch::factory()->for(Supplement::factory()->create(['name' => $name]))->create(['status' => $status] + $research);
}

it('shows only active rows under In progress, with the step', function () {
    $alpha = catalogRow('Alpha', ResearchStatus::Complete);
    $beta = catalogRow('Beta', ResearchStatus::Complete);
    $gamma = catalogRow('Gamma', ResearchStatus::Searching);

    Livewire::test('supplements::catalog')
        ->assertSee('In progress')
        ->set('status', 'active')
        ->assertSeeHtml('wire:key="supplement-'.$gamma->supplement_id.'"')
        ->assertSee('Searching sources')
        ->assertSee('step 2 of 4')
        ->assertDontSeeHtml('wire:key="supplement-'.$alpha->supplement_id.'"')
        ->assertDontSeeHtml('wire:key="supplement-'.$beta->supplement_id.'"');
});

it('shows chip counts and the Failed chip only when something failed', function () {
    catalogRow('Alpha', ResearchStatus::Complete);
    catalogRow('Gamma', ResearchStatus::Queued);

    $component = Livewire::test('supplements::catalog')->assertDontSee('Failed');
    expect($component->get('counts'))->toBe(['all' => 2, 'complete' => 1, 'active' => 1, 'failed' => 0]);

    catalogRow('Delta', ResearchStatus::Failed, ['error' => 'No sources']);

    Livewire::test('supplements::catalog')->assertSee('Failed')->assertSee('No sources')->assertSee('Retry');
});

it('falls back to All for an unknown status and resets pagination on a filter', function () {
    catalogRow('Alpha', ResearchStatus::Complete);

    Livewire::withQueryParams(['status' => 'bogus'])->test('supplements::catalog')
        ->assertSet('status', 'all')
        ->assertSee('Alpha');

    Livewire::test('supplements::catalog')->set('status', 'bogus')->assertSet('status', 'all');
});

it('previews the selected supplement top three effects, strongest first', function () {
    $research = catalogRow('Methylfolate', ResearchStatus::Complete, ['completed_at' => now()]);
    foreach ([['Limited one', EvidenceGrade::Limited], ['Moderate one', EvidenceGrade::Moderate], ['Strong one', EvidenceGrade::Strong], ['Dropped fourth', EvidenceGrade::Limited]] as [$title, $grade]) {
        SupplementEffect::factory()->for($research, 'research')->create(['title' => $title, 'evidence_grade' => $grade]);
    }
    $other = catalogRow('Aardvark', ResearchStatus::Complete);

    Livewire::withQueryParams(['selected' => $research->supplement_id])->test('supplements::catalog')
        ->assertSet('selectedId', $research->supplement_id)
        ->assertSeeInOrder(['TOP EFFECTS IN', 'METHYLFOLATE', 'Strong one', 'Moderate one', 'Limited one'])
        ->assertDontSee('Dropped fourth')
        ->call('selectSupplement', $other->supplement_id)
        ->assertSeeInOrder(['TOP EFFECTS IN', 'AARDVARK']);
});

it('previews the first complete supplement for an unknown selection, or the empty state', function () {
    Livewire::withQueryParams(['selected' => 99999])->test('supplements::catalog')
        ->assertSee('No supplements yet')
        ->assertSee('Nothing to preview');

    catalogRow('Queued one', ResearchStatus::Queued);
    catalogRow('Zinc', ResearchStatus::Complete);

    Livewire::withQueryParams(['selected' => 99999])->test('supplements::catalog')
        ->assertSee('TOP EFFECTS IN')
        ->assertSee('ZINC');
});

it('keeps phones on the supplement page and offers the preview only from md up', function () {
    $research = catalogRow('Zinc', ResearchStatus::Complete);

    $this->get('/supplements')
        ->assertSee('href="'.route('supplements.show', $research->supplement_id).'"', false)
        ->assertSeeHtml('hidden md:block');
});

it('survives a non-numeric selected parameter', function () {
    catalogRow('Zinc', ResearchStatus::Complete);

    $this->get('/supplements?selected=abc')->assertOk()->assertSee('ZINC');
});
