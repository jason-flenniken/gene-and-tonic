<?php

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    RateLimiter::clear('login:'.config('auth.demo.email').'|127.0.0.1');
});

it('signs in the seeded demo user and redirects', function () {
    $this->seed(DemoUserSeeder::class);
    $before = session()->getId();

    Livewire::test('pages::login')
        ->set('email', config('auth.demo.email'))
        ->set('password', config('auth.demo.password'))
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('genetics.profile'));

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->email)->toBe(config('auth.demo.email'))
        ->and(session()->getId())->not->toBe($before);
});

it('seeds exactly one demo user however often it runs', function () {
    $this->seed(DemoUserSeeder::class);
    $this->seed(DemoUserSeeder::class);

    expect(User::count())->toBe(1);

    Livewire::test('pages::login')
        ->set('email', config('auth.demo.email'))
        ->set('password', config('auth.demo.password'))
        ->call('login')
        ->assertRedirect();

    expect(auth()->check())->toBeTrue();
});

it('shows the user name and a sign out button in the header', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)->get('/')->assertSee('Ada Lovelace')->assertSee('Sign out');
});

it('shows a sign in link to guests', function () {
    $this->get('/')->assertSee('Sign in')->assertSee(route('login'));
});

it('logs out via POST and redirects home', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect(route('home'));

    expect(auth()->check())->toBeFalse();
});

it('rejects a wrong password and stays a guest', function () {
    $this->seed(DemoUserSeeder::class);

    Livewire::test('pages::login')
        ->set('email', config('auth.demo.email'))
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertSet('password', '');

    expect(auth()->check())->toBeFalse();
});

it('throttles the 6th failed attempt even with the correct password', function () {
    $this->seed(DemoUserSeeder::class);
    $email = config('auth.demo.email');

    $component = Livewire::test('pages::login')->set('email', $email);

    foreach (range(1, 5) as $ignored) {
        $component->set('password', 'wrong-password')->call('login')->assertHasErrors(['email']);
    }

    $component->set('password', config('auth.demo.password'))
        ->call('login')
        ->assertHasErrors(['email'])
        ->assertSee('Too many requests, try again in a minute.');

    expect(auth()->check())->toBeFalse();
});

it('keys the throttle on lowercased email so casing does not evade it', function () {
    $this->seed(DemoUserSeeder::class);

    foreach (range(1, 5) as $i) {
        Livewire::test('pages::login')
            ->set('email', $i % 2 ? 'DEMO@example.com' : 'demo@example.com')
            ->set('password', 'wrong-password')
            ->call('login');
    }

    Livewire::test('pages::login')
        ->set('email', 'Demo@Example.com')
        ->set('password', config('auth.demo.password'))
        ->call('login')
        ->assertSee('Too many requests');

    expect(auth()->check())->toBeFalse();
    RateLimiter::clear('login:demo@example.com|127.0.0.1');
});

it('redirects authenticated users away from the login page', function () {
    $this->actingAs(User::factory()->create())->get('/login')->assertRedirect();
});

it('does not allow GET /logout', function () {
    $this->actingAs(User::factory()->create())->get('/logout')->assertStatus(405);
});

it('requires authentication for POST /logout', function () {
    $this->post('/logout')->assertRedirect(route('login'));
});
