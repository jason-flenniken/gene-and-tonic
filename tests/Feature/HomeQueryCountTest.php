<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Genetics\Models\UserVariant;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;

beforeEach(function () {
    $this->withoutVite();
    FakeTextGenerator::install();
});

function homeQueries(callable $callback): array
{
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $callback();

    return $queries;
}

function queriesOn(array $queries, string $table): int
{
    return count(array_filter($queries, fn (string $sql): bool => (bool) preg_match('/from [`"]'.$table.'[`"]/', $sql)));
}

function seedHome(User $user, int $supplements): void
{
    foreach (range(1, $supplements) as $i) {
        $supplement = Supplement::factory()->create();
        SupplementResearch::factory()->create(['supplement_id' => $supplement->id, 'status' => ResearchStatus::Complete]);
    }

    foreach (['rs1801133' => 'AG', 'rs1805087' => 'AG', 'rs1801394' => 'GG'] as $rsid => $genotype) {
        UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => $rsid, 'genotype' => $genotype]);
    }
}

it('decrypts the genetic profile at most once per component render', function (string $component) {
    $user = User::factory()->create();
    seedHome($user, 4);

    $queries = homeQueries(fn () => Livewire::actingAs($user)->test($component));

    expect(queriesOn($queries, 'user_variants'))->toBeLessThanOrEqual(1);
})->with([
    'variant-stat' => ['genetics::variant-stat'],
    'match-stat' => ['recommendations::match-stat'],
]);

it('decrypts the profile at most twice in your-genes: its own read, then the shared Recommendations reader', function () {
    $user = User::factory()->create();
    seedHome($user, 4);

    $queries = homeQueries(fn () => Livewire::actingAs($user)->test('genetics::your-genes'));

    expect(queriesOn($queries, 'user_variants'))->toBeLessThanOrEqual(2);
});

it('keeps Home queries flat as supplements grow from 1 to 4', function () {
    $user = User::factory()->create();
    seedHome($user, 1);
    $one = homeQueries(fn () => $this->actingAs($user)->get('/')->assertOk());

    app()->forgetScopedInstances(); // a new request gets fresh scoped readers

    Supplement::factory()->count(3)->create()->each(
        fn (Supplement $supplement) => SupplementResearch::factory()->create(['supplement_id' => $supplement->id, 'status' => ResearchStatus::Complete]),
    );
    $four = homeQueries(fn () => $this->actingAs($user)->get('/')->assertOk());

    expect(count($four))->toBe(count($one));
});
