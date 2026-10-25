<?php

use Modules\Intelligence\Contracts\ProfileRegistry;
use Modules\Intelligence\Enums\UsageWindow;
use Modules\Intelligence\Models\AiRequest;
use Modules\Intelligence\Repositories\AiRequestRepository;

function seedWindowRows(): void
{
    AiRequest::factory()->ago('2 hours')->create(['estimated_cost_usd' => 1]);
    AiRequest::factory()->ago('2 hours')->search(8)->create(['estimated_cost_usd' => 0.008]);
    AiRequest::factory()->ago('3 days')->failed()->create(['estimated_cost_usd' => 2]);
    AiRequest::factory()->ago('30 days')->create(['estimated_cost_usd' => 4]);
}

it('counts summary rows per window', function () {
    seedWindowRows();
    $repo = app(AiRequestRepository::class);

    expect($repo->summary(UsageWindow::Day)->calls)->toBe(2)
        ->and($repo->summary(UsageWindow::Week)->calls)->toBe(3)
        ->and($repo->summary(UsageWindow::Week)->failedCalls)->toBe(1)
        ->and($repo->summary()->calls)->toBe(4)
        ->and($repo->summary(UsageWindow::Day)->estimatedCostUsd)->toBe(1.008);
});

it('paginates only rows inside the window', function () {
    seedWindowRows();
    $repo = app(AiRequestRepository::class);

    expect($repo->paginateLatest(10, UsageWindow::Day)->total())->toBe(2)
        ->and($repo->paginateLatest(10, UsageWindow::Week)->total())->toBe(3)
        ->and($repo->paginateLatest(10)->total())->toBe(4);
});

it('aggregates cost by profile and operation including search credits', function () {
    seedWindowRows();
    $repo = app(AiRequestRepository::class);

    $day = collect($repo->costByProfile(UsageWindow::Day))->keyBy(fn ($r) => $r->operation);
    expect($day)->toHaveCount(2)
        ->and($day['generate']->profile)->toBe('supplements.research')
        ->and($day['generate']->calls)->toBe(1)
        ->and($day['search']->profile)->toBeNull()
        ->and($day['search']->credits)->toBe(8)
        ->and($day['search']->cost)->toBe(0.008);

    $all = $repo->costByProfile();
    expect($all)->toHaveCount(2)
        ->and($all[0]->operation)->toBe('generate')
        ->and($all[0]->calls)->toBe(3)
        ->and($all[0]->failed)->toBe(1)
        ->and($all[0]->cost)->toBe(7.0);
});

it('exposes window labels and boundaries', function () {
    expect(UsageWindow::All->since())->toBeNull()
        ->and(UsageWindow::Day->since()->lt(now()))->toBeTrue()
        ->and(UsageWindow::Week->label())->toBe('7 days')
        ->and(UsageWindow::Day->label())->toBe('24 hours')
        ->and(UsageWindow::All->label())->toBe('All time');
});

it('labels configured profiles and falls back without throwing', function () {
    $registry = app(ProfileRegistry::class);

    expect($registry->get('supplements.research')->label)->toBe('Supplement research')
        ->and($registry->labelFor('supplements.research', 'generate'))->toBe('Supplement research')
        ->and($registry->labelFor('genetics.lookup', 'generate'))->toBe('Gene lookup')
        ->and($registry->labelFor('genetics.group', 'generate'))->toBe('Variant grouping')
        ->and($registry->labelFor('genetics.extract', 'generate'))->toBe('Marker extraction')
        ->and($registry->labelFor('retired.profile', 'generate'))->toBe('retired.profile')
        ->and($registry->labelFor(null, 'search'))->toBe('Web search')
        ->and($registry->labelFor(null, 'generate'))->toBe('Unknown');
});
