<?php

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Modules\Supplements\Models\Supplement;

beforeEach(fn () => $this->withoutVite());

it('serves the public pages to a guest', function () {
    $supplement = Supplement::factory()->create();

    foreach (['/', '/supplements', '/supplements/'.$supplement->id, '/usage'] as $url) {
        $this->get($url)->assertOk();
    }
});

it('titles each page', function (string $url, string $title) {
    $this->get($url)->assertOk()->assertSee('<title>'.$title.' - ', false);
})->with([
    ['/', 'Home'],
    ['/supplements', 'Supplements'],
    ['/usage', 'AI usage'],
]);

it('redirects a guest from genetics to login', function () {
    $this->get('/genetics')->assertRedirect(route('login'));
});

it('renders the genetics page for a signed-in user', function () {
    $this->actingAs(User::factory()->create())->get('/genetics')
        ->assertOk()
        ->assertSee('Upload results')
        ->assertSee('<title>My genetics - ', false);
});

it('lands on genetics after login when genetics was the intended page', function () {
    RateLimiter::clear('login:'.config('auth.demo.email').'|127.0.0.1');
    $this->seed(DemoUserSeeder::class);

    $this->get('/genetics')->assertRedirect(route('login'));

    Livewire::test('pages::login')
        ->set('email', config('auth.demo.email'))
        ->set('password', config('auth.demo.password'))
        ->call('login')
        ->assertRedirect(route('genetics.profile'));
});

it('shows four nav links and Sign in to a guest', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('href="'.route('home').'"', false)
        ->assertSee('href="'.route('supplements.index').'"', false)
        ->assertSee('href="'.route('genetics.profile').'"', false)
        ->assertSee('href="'.route('usage').'"', false)
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('Sign in')
        ->assertDontSee('action="'.route('logout').'"', false);
});

it('shows the nav, name and logout form to a signed-in user', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Sign out')
        ->assertSee('action="'.route('logout').'"', false)
        ->assertDontSee('Sign in');
});

it('renders the mobile top bar with its menu toggle', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('data-flux-header', false)
        ->assertSee('data-flux-sidebar-toggle', false);
});

/**
 * @return list<string> labels of the nav links carrying aria-current="page"
 */
function activeNavLabels(string $html): array
{
    preg_match_all('#<a\s[^>]*aria-current="page"[^>]*>(.*?)</a>#s', $html, $matches);

    return array_map(fn (string $inner) => trim(strip_tags($inner)), $matches[1]);
}

it('marks only the current nav item', function (string $url, string $label) {
    expect(activeNavLabels($this->actingAs(User::factory()->create())->get($url)->assertOk()->getContent()))->toBe([$label]);
})->with([
    ['/', 'Home'],
    ['/supplements', 'Supplements'],
    ['/genetics', 'Genetics'],
    ['/usage', 'AI usage'],
]);

it('keeps Supplements active on a supplement page', function () {
    $supplement = Supplement::factory()->create();

    expect(activeNavLabels($this->get('/supplements/'.$supplement->id)->assertOk()->getContent()))->toBe(['Supplements']);
});

it('links back to the supplements index from a supplement page', function () {
    $supplement = Supplement::factory()->create();

    $this->get('/supplements/'.$supplement->id)->assertSee('href="'.route('supplements.index').'"', false);
});
