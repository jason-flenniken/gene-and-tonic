<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sign in')] class extends Component
{
    private const MAX_ATTEMPTS = 5;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Too many requests, try again in a minute.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            $this->reset('password');

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $this->redirectIntended(
            Route::has('genetics.profile') ? route('genetics.profile') : route('home'),
        );
    }

    private function throttleKey(): string
    {
        return 'login:'.Str::lower($this->email).'|'.request()->ip();
    }
};
?>

<div class="mx-auto max-w-sm space-y-6">
    <flux:heading size="xl">Sign in</flux:heading>

    <form wire:submit="login" class="space-y-4">
        <flux:input wire:model="email" type="email" label="Email" autocomplete="email" required autofocus />
        <flux:input wire:model="password" type="password" label="Password" autocomplete="current-password" required viewable />
        <flux:checkbox wire:model="remember" label="Remember me" />
        <flux:button type="submit" variant="primary" class="w-full">Sign in</flux:button>
    </form>
</div>
