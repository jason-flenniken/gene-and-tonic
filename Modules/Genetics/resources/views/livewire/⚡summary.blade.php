<?php

use Livewire\Component;

new class extends Component {};
?>

<div>
    @auth
        <livewire:genetics::profile />
    @else
        <flux:card class="space-y-4">
            <flux:heading size="lg">My genetics</flux:heading>
            <flux:text>Sign in to add your genetic results.</flux:text>
            <flux:link href="{{ route('login') }}">Sign in</flux:link>
        </flux:card>
    @endauth
</div>
