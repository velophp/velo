<?php

use App\Livewire\CollectionPage;
use App\Models\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::prefix('_')->group(function () {
    Route::middleware(['throttle:60,1'])->group(function () {
        Volt::route('login', 'login')->name('login');
        Volt::route('register', 'register')->name('register');
    });

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('', function (): RedirectResponse {
            $collection = Collection::firstOrFail();

            return redirect()->route('collections', ['collection' => $collection]);
        })->name('home');

        Volt::route('collections/superusers', 'manage-superusers')->name('collections.superusers');

        // Volt::route('collections/{collection:name}', 'collection')->name('collection');
        Route::get('collections/{collection:name}', CollectionPage::class)->name('collections');

        Route::get('logout', function () {
            Auth::logout();
            session()->regenerate(destroy: true);

            return redirect(route('login'));
        })->name('logout');
    });
});
