<?php

use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->withoutVite());

it('renders Home with the overview panel', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('data-panel="overview"', false)
        ->assertDontSee('role="tab"', false);
});

it('redirects each old tab link to its route', function (string $tab, string $route) {
    $this->get('/?tab='.$tab)->assertRedirect(route($route));
})->with([
    'supplements' => ['supplements', 'supplements.index'],
    'genetics' => ['genetics', 'genetics.profile'],
    'usage' => ['usage', 'usage'],
]);

it('renders Home for the overview tab, an unknown tab and an array tab without redirecting', function (string $query) {
    $this->get('/'.$query)->assertOk()->assertSee('data-panel="overview"', false);
})->with(['?tab=overview', '?tab=garbage', '?tab[]=genetics', '?tab=']);

it('mounts the home page component without a redirect', function () {
    Livewire::test('pages::dashboard')->assertNoRedirect();
});

it('sends a guest from the old genetics tab to login, not the genetics markup', function () {
    $this->followingRedirects()->get('/?tab=genetics')
        ->assertOk()
        ->assertDontSee('Upload results')
        ->assertDontSee('data-panel="genetics"', false);
});

it('never renders genetics markup on Home for a guest', function () {
    $this->get('/')->assertDontSee('Upload results')->assertDontSee('Add SNP');
});

it('links the brand in the sidebar back to Home', function () {
    $this->get('/supplements')->assertOk()->assertSee('href="'.route('home').'"', false);
});

it('does not use the old tab links to home anywhere', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/genetics')->assertOk()->assertDontSee('?tab=', false);
});
