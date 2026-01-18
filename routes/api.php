<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecordController;
use Illuminate\Support\Facades\Route;

/*
 * API Routes
 * !IMPORTANT: Request MUST send Accept headers of application/json when using laravel validation else laravel will throw a web 404 response.
 */
Route::prefix('collections/{collection:name}')->group(function () {
    Route::prefix('/auth')->middleware(['throttle:60,1'])->name('auth.')->group(function () {
        Route::post('/authenticate-with-password', [AuthController::class, 'authenticateWithPassword'])->name('authenticate-with-password');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('/confirm-password-reset', [AuthController::class, 'confirmPasswordReset'])->name('confirm-password-reset');
        Route::post('/request-auth-otp', [AuthController::class, 'requestAuthOtp'])->name('request-otp');
        Route::post('/authenticate-with-otp', [AuthController::class, 'authenticateWithOtp'])->name('authenticate-with-otp');
    });

    Route::prefix('/records')->name('records.')->group(function () {
        Route::get('/', [RecordController::class, 'list'])->name('list');
        Route::get('/{recordId}', [RecordController::class, 'view'])->name('view');
        Route::post('/', [RecordController::class, 'create'])->name('create');
        Route::put('/{recordId}', [RecordController::class, 'update'])->name('update');
        Route::delete('/{recordId}', [RecordController::class, 'delete'])->name('delete');
    });
});
