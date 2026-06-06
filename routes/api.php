<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/me', MeController::class)->name('api.me');
});
