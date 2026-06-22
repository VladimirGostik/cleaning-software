<?php

declare(strict_types=1);

use App\Http\Controllers\Api\IcoLookupController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationBellController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/me', MeController::class)->name('api.me');
    Route::get('/notifications/bell', NotificationBellController::class)->name('api.notifications.bell');
});

// IČO lookup — guest-accessible (pre-auth registration + authenticated add-company modal).
Route::middleware('throttle:ico-lookup')->group(function (): void {
    Route::get('/icos/{ico}', IcoLookupController::class)
        ->whereNumber('ico')
        ->name('api.icos.lookup');
});
