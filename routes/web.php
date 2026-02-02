<?php

use App\Delivery\Http\Controllers\StorageController;
use App\Domain\Collection\Models\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('')->group(function () {
    Route::livewire('login', 'pages::login')->name('login');
    Route::livewire('register', 'pages::register')->name('register');
    Route::livewire('forgot-password', 'pages::forgot-password')->name('password.request');
    Route::livewire('reset-password/{token}', 'pages::reset-password')->name('password.reset');

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('', function (): RedirectResponse {
            $collection = Collection::firstOrFail();

            return redirect()->route('collections', ['collection' => $collection]);
        })->name('home');

        Route::livewire('system/superusers', 'pages::manage-system-collection')->name('system.superusers');
        Route::livewire('system/sessions', 'pages::manage-system-collection')->name('system.sessions');
        Route::livewire('system/otps', 'pages::manage-system-collection')->name('system.otps');
        Route::livewire('system/password-resets', 'pages::manage-system-collection')->name('system.password.resets');
        Route::livewire('system/realtime', 'pages::manage-system-collection')->name('system.realtime');

        Route::livewire('system/settings', 'pages::settings')->name('system.settings');
        Route::livewire('system/logs', 'pages::logs')->name('system.logs');

        Route::livewire('collections/{collection:name}', 'pages::collection')->name('collections');

        Route::get('logout', function () {
            Auth::logout();
            session()->regenerate(destroy: true);

            return redirect(route('login'));
        })->name('logout');
    });
});

Route::get('/files/{path}', [StorageController::class, 'intercept'])
    ->where('path', '.*');
