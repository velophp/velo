<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::prefix('_')->group(function () {

    Route::middleware(['auth', 'verified'])->group(function () {
        Volt::route('collections/{collection:name}', 'collection')->name('collection');
        Route::redirect('', '/_/collections/users')->name('home');

        Route::get('logout', function () {
            Auth::logout();
            session()->regenerate(destroy: true);
            return redirect(route('login'));
        })->name('logout');
    });

    Route::middleware(['throttle:60,1'])->group(function () {
        Volt::route('login', 'login')->name('login');
        Volt::route('register', 'register')->name('register');
    });


});
