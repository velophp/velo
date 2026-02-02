<?php

use App\Delivery\Http\Controllers\AuthController;
use App\Delivery\Http\Controllers\RealtimeController;
use App\Delivery\Http\Controllers\RecordController;
use Illuminate\Support\Facades\Route;

/*
 * API Routes
 * !IMPORTANT: Request MUST send Accept headers of application/json when using laravel validation else laravel will throw a web 404 response.
 */
Route::prefix('collections/{collection:name}')->group(function () {
    Route::prefix('/auth')->middleware(['throttle:dynamic-api'])->name('auth.')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/authenticate-with-password', [AuthController::class, 'authenticateWithPassword'])->name('authenticate-with-password');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('/confirm-forgot-password', [AuthController::class, 'confirmForgotPassword'])->name('confirm-forgot-password');
        Route::post('/request-auth-otp', [AuthController::class, 'requestAuthOtp'])->name('request-otp');
        Route::post('/authenticate-with-otp', [AuthController::class, 'authenticateWithOtp'])->name('authenticate-with-otp');
        Route::post('/request-update-email', [AuthController::class, 'requestUpdateEmail'])->name('request-update-email');
        Route::post('/confirm-update-email', [AuthController::class, 'confirmUpdateEmail'])->name('confirm-update-email');
    });

    Route::prefix('/records')->name('records.')->group(function () {
        Route::get('/', [RecordController::class, 'list'])->name('list');
        Route::get('/{recordId}', [RecordController::class, 'view'])->name('view');
        Route::post('/', [RecordController::class, 'create'])->name('create');
        Route::match(['put', 'patch'], '/{recordId}', [RecordController::class, 'update'])->name('update');
        Route::delete('/{recordId}', [RecordController::class, 'delete'])->name('delete');
    });
});

Route::prefix('realtime')->name('realtime.')->group(function () {
    Route::post('/subscribe', [RealtimeController::class, 'subscribe'])->name('subscribe');
    Route::post('/ping', [RealtimeController::class, 'ping'])->name('ping');
});
