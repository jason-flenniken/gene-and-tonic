<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/supplements/{supplement}', 'supplements::show')
    ->whereNumber('supplement')
    ->name('supplements.show');
