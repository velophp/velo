<?php

use App\Models\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('_')->group(function () {
    Route::livewire('login', 'pages::login')->name('login');
    Route::livewire('register', 'pages::register')->name('register');
    Route::livewire('forgot-password', 'pages::forgot-password')->name('password.request');
    Route::livewire('reset-password/{token}', 'pages::reset-password')->name('password.reset');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('', function (): RedirectResponse {
            $collection = Collection::firstOrFail();

            return redirect()->route('collections', ['collection' => $collection]);
        })->name('home');

        Route::livewire('system/superusers', 'pages::manage-superusers')->name('system.superusers');
        Route::livewire('system/sessions', 'pages::manage-auth-sessions')->name('system.sessions');
        Route::livewire('system/otps', 'pages::manage-otps')->name('system.otps');
        Route::livewire('system/password-resets', 'pages::manage-password-resets')->name('system.password.resets');
        Route::redirect('system/logs', '/_/pulse')->name('system.logs');

        Route::livewire('collections/{collection:name}', 'pages::collection')->name('collections');

        Route::get('logout', function () {
            Auth::logout();
            session()->regenerate(destroy: true);

            return redirect(route('login'));
        })->name('logout');
    });
});
