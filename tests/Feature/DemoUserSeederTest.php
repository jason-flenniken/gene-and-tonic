<?php

use App\Models\User;
use Database\Seeders\DemoUserSeeder;

it('seeds the demo user with the default password in the testing environment', function () {
    (new DemoUserSeeder)->run();

    expect(User::where('email', config('auth.demo.email'))->exists())->toBeTrue();
});

it('seeds the demo user with the default password in the local environment', function () {
    app()->detectEnvironment(fn (): string => 'local');

    (new DemoUserSeeder)->run();

    expect(User::where('email', config('auth.demo.email'))->exists())->toBeTrue();
});

it('refuses to seed outside local and testing with the default or a missing password', function (?string $password) {
    app()->detectEnvironment(fn (): string => 'production');
    config(['auth.demo.password' => $password]);

    (new DemoUserSeeder)->run();

    expect(User::count())->toBe(0);
})->with([
    'default password' => ['password'],
    'empty password' => [''],
    'unset password' => [null],
]);

it('seeds outside local and testing when a strong password is set explicitly', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['auth.demo.password' => 'a-long-unguessable-passphrase']);

    (new DemoUserSeeder)->run();

    $user = User::where('email', config('auth.demo.email'))->first();
    expect($user)->not->toBeNull()
        ->and(Hash::check('a-long-unguessable-passphrase', $user->password))->toBeTrue();
});
