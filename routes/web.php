<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::dashboard')->name('home');

Route::livewire('/login', 'pages::login')->middleware('guest')->name('login');

Route::post('/logout', function (Request $request): RedirectResponse {
    Auth::guard()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');
