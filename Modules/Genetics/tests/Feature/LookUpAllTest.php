<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Genetics\Actions\LookUpAll;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Jobs\LookupGeneVariant;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;

beforeEach(function () {
    Queue::fake();
});

function ownedRow(User $user, string $rsid, LookupStatus $status = LookupStatus::Pending): GeneVariant
{
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => $rsid]);

    return GeneVariant::factory()->pending()->create(['rsid' => $rsid, 'status' => $status]);
}

it('queues up to the cap and reports the count', function () {
    config(['genetics.lookup_cap' => 15]);
    $user = User::factory()->create();
    foreach (range(1, 20) as $i) {
        ownedRow($user, "rs{$i}");
    }

    $result = app(LookUpAll::class)->handle($user->id);

    expect($result->queued)->toBe(15)
        ->and($result->limited)->toBeFalse()
        ->and($result->nothingPending)->toBeFalse()
        ->and(GeneVariant::where('status', LookupStatus::LookingUp)->count())->toBe(15)
        ->and(GeneVariant::where('status', LookupStatus::Pending)->count())->toBe(5);
    Queue::assertPushed(LookupGeneVariant::class, 15);
});

it('queues failed rows too', function () {
    $user = User::factory()->create();
    ownedRow($user, 'rs1', LookupStatus::Failed);

    expect(app(LookUpAll::class)->handle($user->id)->queued)->toBe(1);
});

it('reports nothingPending and dispatches nothing when no row is pending or failed', function () {
    $user = User::factory()->create();
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs1']);
    GeneVariant::factory()->create(['rsid' => 'rs1', 'status' => LookupStatus::Complete]);

    $result = app(LookUpAll::class)->handle($user->id);

    expect($result->nothingPending)->toBeTrue()->and($result->queued)->toBe(0);
    Queue::assertNothingPushed();
});

it('never queues another user\'s pending rsid', function () {
    [$a, $b] = User::factory()->count(2)->create();
    ownedRow($b, 'rs999');
    ownedRow($a, 'rs1');

    $result = app(LookUpAll::class)->handle($a->id);

    expect($result->queued)->toBe(1)
        ->and(GeneVariant::where('rsid', 'rs999')->value('status'))->toBe(LookupStatus::Pending);
    Queue::assertPushed(LookupGeneVariant::class, 1);
    Queue::assertNotPushed(LookupGeneVariant::class, fn ($job) => $job->rsid === 'rs999');
});

it('does not queue rows that are already being looked up', function () {
    $user = User::factory()->create();
    ownedRow($user, 'rs1', LookupStatus::LookingUp);

    $result = app(LookUpAll::class)->handle($user->id);

    expect($result->nothingPending)->toBeTrue();
    Queue::assertNothingPushed();
});

it('limits a fourth call within a minute', function () {
    $user = User::factory()->create();
    RateLimiter::clear('genetics-lookup-all:'.$user->id);

    foreach (range(1, 3) as $i) {
        ownedRow($user, "rs{$i}");
        expect(app(LookUpAll::class)->handle($user->id)->limited)->toBeFalse();
    }
    ownedRow($user, 'rs4');
    Queue::fake();

    $result = app(LookUpAll::class)->handle($user->id);

    expect($result->limited)->toBeTrue()->and($result->queued)->toBe(0)
        ->and(GeneVariant::where('rsid', 'rs4')->value('status'))->toBe(LookupStatus::Pending);
    Queue::assertNothingPushed();
});
