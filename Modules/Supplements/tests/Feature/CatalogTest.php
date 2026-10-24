<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Supplements\Actions\AddSupplement;
use Modules\Supplements\Actions\StartResearch;
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

it('renders the catalog on the dashboard tab with rows and links', function () {
    $supplement = Supplement::factory()->create(['name' => 'Zinc']);
    $research = SupplementResearch::factory()->for($supplement)->create(['status' => ResearchStatus::Complete]);
    SupplementEffect::factory()->count(2)->for($research, 'research')->create();
    SupplementStudy::factory()->for($research, 'research')->create();

    $this->get('/?tab=supplements')
        ->assertOk()
        ->assertSee('Zinc')
        ->assertSee('Complete')
        ->assertSee('/supplements/'.$supplement->id);

    Livewire::test('supplements::catalog')->assertSeeInOrder(['Zinc', 'Complete', '2', '1']);
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

        public function paginateWithResearch(int $perPage): LengthAwarePaginator
        {
            return $this->inner->paginateWithResearch($perPage);
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

it('runs the placeholder job without error', function () {
    (new RunSupplementResearch(1))->handle();
    expect(true)->toBeTrue();
});
