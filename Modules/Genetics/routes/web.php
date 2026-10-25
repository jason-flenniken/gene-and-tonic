<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/genetics', '/?tab=genetics')->middleware('auth')->name('genetics.profile');
