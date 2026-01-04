<?php

use Livewire\Volt\Volt;
use App\Models\Collection;
use App\Livewire\CollectionPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {

    Route::prefix('_')->group(function () {
        // Volt::route('collections/{collection:name}', 'collection')->name('collection');
        Route::get('collections/{collection:name}', CollectionPage::class)->name('collection');

        Route::get('logout', function () {
            Auth::logout();
            session()->regenerate(destroy: true);
            return redirect(route('login'));
        })->name('logout');

        Route::middleware(['throttle:60,1'])->group(function () {
            Volt::route('login', 'login')->name('login');
            Volt::route('register', 'register')->name('register');
        });
    });


    Route::get('/{path}', function (): RedirectResponse {
        $collection = Collection::first();
        return redirect()->route('collection', ['collection' => $collection]);
    })->where('path', '|_');
    Route::redirect('_', '/')->name('home');

});
