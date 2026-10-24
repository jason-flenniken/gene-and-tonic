<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/genetics', 'genetics::profile')->middleware('auth')->name('genetics.profile');
