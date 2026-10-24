<?php

use Livewire\Livewire;

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
