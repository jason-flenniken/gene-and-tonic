@php
    $navItems = [
        ['label' => 'Home', 'href' => route('home'), 'active' => request()->routeIs('home'), 'icon' => 'home'],
        ['label' => 'Supplements', 'href' => route('supplements.index'), 'active' => request()->routeIs('supplements.*'), 'icon' => 'capsule'],
        ['label' => 'Genetics', 'href' => route('genetics.profile'), 'active' => request()->routeIs('genetics.*'), 'icon' => 'helix'],
        ['label' => 'AI usage', 'href' => route('usage'), 'active' => request()->routeIs('usage'), 'icon' => 'chart'],
    ];
@endphp
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
        <div class="flex min-h-screen flex-col lg:flex-row">
        <flux:header class="lg:hidden !bg-canvas">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-lg font-bold tracking-tight text-ink">
                <x-brand.mark :size="34" />
                <span>{{ config('app.name', 'Laravel') }}</span>
            </a>

            <flux:spacer />

            <flux:sidebar.toggle icon="bars-2" inset="right" aria-label="Open menu" />
        </flux:header>

        <flux:sidebar collapsible="mobile" class="!border-r-0 !bg-canvas lg:!w-[210px] lg:shrink-0 lg:!px-5 lg:!py-7">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-lg font-bold tracking-tight text-ink">
                <x-brand.mark :size="34" />
                <span>{{ config('app.name', 'Laravel') }}</span>
            </a>

            <nav aria-label="Main" class="mt-8 flex flex-col gap-1">
                @foreach ($navItems as $item)
                    <a
                        href="{{ $item['href'] }}"
                        @if ($item['active']) aria-current="page" @endif
                        @class([
                            'flex min-h-11 items-center gap-3 rounded-row px-2 text-base',
                            'font-bold text-juniper' => $item['active'],
                            'font-medium text-ink-nav hover:text-ink' => ! $item['active'],
                        ])
                    >
                        <x-dynamic-component :component="'icon.'.$item['icon']" :class="$item['active'] ? 'size-5 shrink-0 [stroke-width:1.9]' : 'size-5 shrink-0'" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <flux:spacer />

            <div class="flex flex-col gap-1">
                @auth
                    <div class="flex min-h-11 items-center gap-3 px-2">
                        <span class="flex size-[26px] shrink-0 items-center justify-center rounded-full bg-[#E3DED6] text-[10px] font-bold text-ink" aria-hidden="true">{{ auth()->user()->initials() }}</span>
                        <span class="truncate text-base font-medium text-ink">{{ auth()->user()->name }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex min-h-11 w-full items-center rounded-row px-2 text-sm font-semibold text-ink-nav hover:text-ink">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="flex min-h-11 items-center px-2 text-sm font-semibold text-juniper hover:underline">Sign in</a>
                @endauth
            </div>
        </flux:sidebar>

        <main class="min-w-0 flex-1 px-[clamp(16px,3vw,40px)] pb-12 pt-6">
            {{ $slot }}
        </main>
        </div>

        <flux:toast />

        @fluxScripts
    </body>
</html>
