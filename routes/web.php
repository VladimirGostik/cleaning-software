<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LanguageController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Landing');
})->name('landing');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:password-reset');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store')->middleware('throttle:password-reset-confirm');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

    // Clients — plan-independent: all subscription tiers (including Free) have access to clients.
    // Explicit named routes per routing.md rule #4 (NEVER Route::resource).
    //
    // Canonical AND-chain pattern for FUTURE plan-gated modules:
    //
    //   Route::middleware(['feature:objects'])->group(function (): void {
    //       Route::get('/objects', [ObjectController::class, 'index'])->name('objects.index');
    //       // ... other actions
    //   });
    //
    // The feature: middleware (plan axis) runs first; the controller's #[Authorize(...)] attribute
    // (user/permission axis) runs second. Both must pass — two single-responsibility gates ANDed
    // by the request lifecycle. Clients intentionally omit feature: to remain Free-plan accessible.
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::match(['PUT', 'PATCH'], '/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
});

Route::get('language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');
