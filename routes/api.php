<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationBellController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'ability:mobile', 'tenant.context', 'tenant.required'])->group(function (): void {
    Route::get('/me', MeController::class);
    Route::get('/dashboard', DashboardController::class)->name('api.dashboard');
    Route::get('/notifications/bell', NotificationBellController::class)->name('api.notifications.bell');

    Route::middleware('throttle:devices')->group(function (): void {
        Route::post('/devices', [DeviceController::class, 'store'])->name('api.devices.store');
        Route::delete('/devices', [DeviceController::class, 'destroy'])->name('api.devices.destroy');
    });

    Route::get('/tasks', [TaskController::class, 'index'])->name('api.tasks.index');
    Route::get('/tasks/form-context', [TaskController::class, 'formContext'])->name('api.tasks.form-context');
    Route::post('/tasks', [TaskController::class, 'store'])->name('api.tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('api.tasks.show')->whereUuid('task');
    Route::match(['PUT', 'PATCH'], '/tasks/{task}', [TaskController::class, 'update'])->name('api.tasks.update')->whereUuid('task');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('api.tasks.destroy')->whereUuid('task');
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('api.tasks.complete')->whereUuid('task');
    Route::post('/tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('api.tasks.reopen')->whereUuid('task');
    Route::post('/tasks/{task}/assign', [TaskController::class, 'assign'])->name('api.tasks.assign')->whereUuid('task');

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'changePassword']);
    });

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::put('/users/{user}', [UserController::class, 'update']);
    });
});
