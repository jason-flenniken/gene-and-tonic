<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Genetics\Actions\AddVariant;
use Modules\Genetics\Models\UserVariant;

beforeEach(fn () => $this->withoutVite());

it('renders the dashboard at the home route', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Overview')
        ->assertSee('Supplements')
        ->assertSee('AI Usage')
        ->assertSee('data-panel="overview"', false);
});

it('shows the supplements panel active for ?tab=supplements', function () {
    $this->get('/?tab=supplements')
        ->assertOk()
        ->assertSee('data-panel="supplements"', false)
        ->assertDontSee('data-panel="overview"', false)
        ->assertSeeInOrder(['role="tab"', 'aria-selected="false"', 'role="tab"', 'aria-selected="true"'], false);
});

it('falls back to overview for an unknown tab', function () {
    $this->get('/?tab=garbage')->assertOk()->assertSee('data-panel="overview"', false);
});

it('updates the url-bound tab property', function () {
    Livewire::test('pages::dashboard')
        ->assertSet('tab', 'overview')
        ->set('tab', 'usage')
        ->assertSet('tab', 'usage')
        ->set('tab', 'nope')
        ->assertSet('tab', 'overview');
});

it('shows a sign-in prompt on the genetics tab for a guest', function () {
    $this->get('/?tab=genetics')
        ->assertOk()
        ->assertSee('data-panel="genetics"', false)
        ->assertSee('Sign in to add your genetic results')
        ->assertDontSee('SNPs on file');
});

it('shows the signed-in user\'s own variants on the genetics tab', function () {
    $user = User::factory()->create();
    app(AddVariant::class)->handle($user->id, 'rs1801133', 'AG');
    app(AddVariant::class)->handle($user->id, 'rs4680', 'AA');
    UserVariant::factory()->create(['rsid' => 'rs999']);

    $this->actingAs($user)->get('/?tab=genetics')
        ->assertOk()
        ->assertSee('rs1801133')
        ->assertSee('rs4680')
        ->assertDontSee('rs999');
});

it('does not show the upload form to a guest on the genetics tab', function () {
    $this->get('/?tab=genetics')
        ->assertOk()
        ->assertSee('Sign in to add your genetic results')
        ->assertDontSee('Upload results')
        ->assertDontSee('Add SNP');
});

it('shows the genetics upload and add-SNP tools inside the dashboard genetics tab', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/?tab=genetics')
        ->assertOk()
        ->assertSee('data-panel="genetics"', false)
        ->assertSee('Upload results')
        ->assertSee('Add SNP');
});

it('sends the old genetics page to the dashboard genetics tab', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/genetics')->assertRedirect(route('home', ['tab' => 'genetics']));
});

it('links the app name in the header back to the dashboard', function () {
    $this->get('/?tab=genetics')->assertOk()->assertSee('href="'.route('home').'"', false);
});
