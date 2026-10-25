<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ isset($title) ? $title.' - ' : '' }}{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <header class="border-b border-zinc-200">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
                <a href="{{ route('home') }}"><flux:heading size="lg">{{ config('app.name', 'Laravel') }}</flux:heading></a>

                @auth
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-zinc-600">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:button type="submit" size="sm" variant="ghost">Sign out</flux:button>
                        </form>
                    </div>
                @else
                    <flux:link href="{{ route('login') }}">Sign in</flux:link>
                @endauth
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            {{ $slot }}
        </main>

        <flux:toast />

        @fluxScripts
    </body>
</html>
