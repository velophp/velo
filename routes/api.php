<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RecordController;
use Illuminate\Support\Facades\Route;

/**
 * API Routes
 * !IMPORTANT: Request MUST send Accept headers of application/json when using laravel validation else laravel will throw a web 404 response.
 */
Route::prefix('collections/{collection:name}')->group(function () {

    Route::prefix('/auth')->name('auth.')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/with-password', [AuthController::class, 'authenticateWithPassword'])->name('with-password');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
    });

    Route::prefix('/records')->name('records.')->group(function () {
        Route::get('/', [RecordController::class, 'list'])->name('list');
        Route::get('/{recordId}', [RecordController::class, 'view'])->name('view');
        Route::post('/', [RecordController::class, 'create'])->name('create');
        Route::put('/{recordId}', [RecordController::class, 'update'])->name('update');
        Route::delete('/{record}', [RecordController::class, 'delete'])->name('delete');
    });

});
