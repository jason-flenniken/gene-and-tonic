<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Genetics\Actions\AddVariant;
use Modules\Genetics\Database\Seeders\GeneVariantSeeder;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;

beforeEach(function () {
    $this->withoutVite();
    Queue::fake();
});

it('redirects a guest from /genetics to the login page', function () {
    $this->get('/genetics')->assertRedirect('/login');
});

it('lists a user\'s variants with gene, genotype, category, affects and effect', function () {
    $this->seed(GeneVariantSeeder::class);
    $user = User::factory()->create();
    app(AddVariant::class)->handle($user->id, 'rs1801133', 'AG');
    app(AddVariant::class)->handle($user->id, 'rs4680', 'AA');
    $mthfr = GeneVariant::where('rsid', 'rs1801133')->sole();

    $this->actingAs($user)->get('/genetics')
        ->assertOk()
        ->assertSee('rs1801133')
        ->assertSee('rs4680')
        ->assertSee('MTHFR')
        ->assertSee($mthfr->category)
        ->assertSee($mthfr->affects)
        ->assertSee($mthfr->genotype_effects['AG'])
        ->assertSee('not medical advice or a diagnosis');
});

it('sorts the list by gene', function () {
    $user = User::factory()->create();
    GeneVariant::factory()->create(['rsid' => 'rs1', 'gene' => 'ZZZ1']);
    GeneVariant::factory()->create(['rsid' => 'rs2', 'gene' => 'AAA1']);
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs1', 'genotype' => 'AG']);
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs2', 'genotype' => 'AG']);

    $this->actingAs($user)->get('/genetics')->assertSeeInOrder(['AAA1', 'ZZZ1']);
});

it('shows "Not looked up yet" for a pending variant', function () {
    $user = User::factory()->create();
    app(AddVariant::class)->handle($user->id, 'rs999', 'AG');

    $this->actingAs($user)->get('/genetics')->assertSee('rs999')->assertSee('Not looked up yet');
});

it('adds a variant by hand, normalising the genotype', function () {
    $this->seed(GeneVariantSeeder::class);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('genetics::profile')
        ->set('rsid', 'rs1801133')
        ->set('genotype', 'g/a')
        ->call('addVariant')
        ->assertHasNoErrors()
        ->assertSet('rsid', '')
        ->assertSee('rs1801133');

    $variant = UserVariant::where('user_id', $user->id)->sole();
    expect($variant->genotype)->toBe('AG')->and($variant->source)->toBe('manual');
});

it('rejects an invalid rsID, genotype or gene and stores nothing', function (string $rsid, string $genotype, string $gene, string $field) {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('genetics::profile')
        ->set('rsid', $rsid)
        ->set('genotype', $genotype)
        ->set('gene', $gene)
        ->call('addVariant')
        ->assertHasErrors($field);

    expect(UserVariant::count())->toBe(0)->and(GeneVariant::count())->toBe(0);
})->with([
    'bad rsid' => ['abc', 'AG', '', 'rsid'],
    'bad genotype' => ['rs1', 'QQ', '', 'genotype'],
    'no-call' => ['rs1', '--', '', 'genotype'],
    'empty' => ['', '', '', 'rsid'],
    'bad gene' => ['rs1', 'AG', 'bad gene!', 'gene'],
]);

it('rejects a guest calling addVariant', function () {
    Livewire::test('genetics::profile')
        ->set('rsid', 'rs1801133')
        ->set('genotype', 'AG')
        ->call('addVariant')
        ->assertUnauthorized();

    expect(UserVariant::count())->toBe(0);
});

it('never shows another user\'s variants', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    app(AddVariant::class)->handle($a->id, 'rs111', 'AG');
    app(AddVariant::class)->handle($b->id, 'rs222', 'CT');

    $this->actingAs($a)->get('/genetics')->assertSee('rs111')->assertDontSee('rs222');
    Livewire::actingAs($a)->test('genetics::profile')->assertSee('rs111')->assertDontSee('rs222')->assertDontSee('CT');
});

it('stops adding after 20 attempts a minute', function () {
    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test('genetics::profile');

    foreach (range(1, 21) as $i) {
        $component->set('rsid', "rs{$i}")->set('genotype', 'AG')->call('addVariant');
    }

    $component->assertHasErrors('rsid');
    expect(UserVariant::count())->toBe(20);
});
