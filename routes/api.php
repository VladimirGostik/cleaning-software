<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AssistantConversationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\NotificationBellController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PendingActionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\TaskAttachmentController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskParseController;
use App\Http\Controllers\Api\TaskRunController;
use App\Http\Controllers\Api\TokenUploadController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

Route::middleware([HandlePrecognitiveRequests::class, 'locale.api', 'throttle:mobile-login'])->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware(['locale.api', 'throttle:mobile-token'])->group(function (): void {
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('api.auth.refresh');
    Route::post('/auth/revoke', [AuthController::class, 'revoke'])->name('api.auth.revoke');
});

Route::middleware(['auth:sanctum', 'locale.api'])->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'locale.api', 'user.active', 'ability:mobile', 'tenant.context', 'tenant.required'])->group(function (): void {
    Route::get('/me', MeController::class);
    Route::get('/dashboard', DashboardController::class)->name('api.dashboard');
    Route::get('/notifications/bell', NotificationBellController::class)->name('api.notifications.bell');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('api.notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('api.notifications.read')->whereUuid('notification');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('api.invoices.index');
    Route::get('/invoices/stats', [InvoiceController::class, 'stats'])->name('api.invoices.stats');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('api.invoices.show')->whereUuid('invoice');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('api.invoices.pdf')->whereUuid('invoice');
    Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('api.invoices.issue')->whereUuid('invoice');
    Route::get('/invoices/{invoice}/send-draft', [InvoiceController::class, 'sendDraft'])->name('api.invoices.send-draft')->whereUuid('invoice');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->middleware('throttle:invoice-send')->name('api.invoices.send')->whereUuid('invoice');

    Route::get('/quotes', [QuoteController::class, 'index'])->name('api.quotes.index');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('api.quotes.show')->whereUuid('quote');
    Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('api.quotes.pdf')->whereUuid('quote');

    Route::middleware('throttle:devices')->group(function (): void {
        Route::post('/devices', [DeviceController::class, 'store'])->name('api.devices.store');
        Route::delete('/devices', [DeviceController::class, 'destroy'])->name('api.devices.destroy');
    });

    Route::post('/assistant/messages', [AssistantController::class, 'store'])->middleware('throttle:assistant')->name('api.assistant.messages');
    Route::middleware('throttle:assistant-history')->group(function (): void {
        Route::get('/assistant/conversations', [AssistantConversationController::class, 'index'])->name('api.assistant.conversations.index');
        Route::get('/assistant/conversations/{conversation}', [AssistantConversationController::class, 'show'])->name('api.assistant.conversations.show')->whereUuid('conversation');
        Route::patch('/assistant/conversations/{conversation}', [AssistantConversationController::class, 'update'])->name('api.assistant.conversations.update')->whereUuid('conversation');
        Route::delete('/assistant/conversations/{conversation}', [AssistantConversationController::class, 'destroy'])->name('api.assistant.conversations.destroy')->whereUuid('conversation');
    });
    Route::post('/tasks/parse', TaskParseController::class)->middleware('throttle:ai-analyze')->name('api.tasks.parse');

    Route::get('/tasks', [TaskController::class, 'index'])->name('api.tasks.index');
    Route::get('/tasks/board', [TaskController::class, 'board'])->name('api.tasks.board');
    Route::get('/tasks/form-context', [TaskController::class, 'formContext'])->name('api.tasks.form-context');
    Route::post('/tasks', [TaskController::class, 'store'])->name('api.tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('api.tasks.show')->whereUuid('task');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('api.tasks.update')->whereUuid('task');
    Route::patch('/tasks/{task}', [TaskController::class, 'patch'])->name('api.tasks.patch')->whereUuid('task');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('api.tasks.destroy')->whereUuid('task');
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('api.tasks.complete')->whereUuid('task');
    Route::post('/tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('api.tasks.reopen')->whereUuid('task');
    Route::post('/tasks/{task}/status', [TaskController::class, 'status'])->name('api.tasks.status')->whereUuid('task');
    Route::post('/tasks/{task}/assign', [TaskController::class, 'assign'])->name('api.tasks.assign')->whereUuid('task');
    Route::get('/tasks/{task}/attachments', [TaskAttachmentController::class, 'index'])->name('api.tasks.attachments.index')->whereUuid('task');
    Route::post('/tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->middleware('throttle:task-attachments')->name('api.tasks.attachments.store')->whereUuid('task');
    Route::get('/tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'show'])->name('api.tasks.attachments.show')->whereUuid(['task', 'attachment']);
    Route::delete('/tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('api.tasks.attachments.destroy')->whereUuid(['task', 'attachment']);
    Route::get('/tasks/{task}/thread', [TaskCommentController::class, 'thread'])->name('api.tasks.thread')->whereUuid('task');
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->middleware('throttle:task-comments')->name('api.tasks.comments.store')->whereUuid('task');
    Route::delete('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('api.tasks.comments.destroy')->whereUuid(['task', 'comment']);
    Route::get('/tasks/{task}/ai-run-status', [TaskRunController::class, 'status'])->name('api.tasks.ai-run-status')->whereUuid('task');
    Route::post('/tasks/{task}/ai-runs', [TaskRunController::class, 'store'])->middleware('throttle:task-ai-runs')->name('api.tasks.ai-runs.store')->whereUuid('task');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('api.expenses.index');
    Route::get('/expenses/form-context', [ExpenseController::class, 'formContext'])->name('api.expenses.form-context');
    Route::middleware('throttle:ai-analyze')->group(function (): void {
        Route::post('/expenses/analyze', [ExpenseController::class, 'analyze'])->name('api.expenses.analyze');
    });
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('api.expenses.store');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('api.expenses.show')->whereUuid('expense');
    Route::match(['PUT', 'PATCH'], '/expenses/{expense}', [ExpenseController::class, 'update'])->name('api.expenses.update')->whereUuid('expense');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('api.expenses.destroy')->whereUuid('expense');
    Route::post('/expenses/{expense}/confirm', [ExpenseController::class, 'confirm'])->name('api.expenses.confirm')->whereUuid('expense');
    Route::post('/expenses/{expense}/receipt', [ExpenseController::class, 'attachReceipt'])->name('api.expenses.attach-receipt')->whereUuid('expense');
    Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->name('api.expenses.receipt')->whereUuid('expense');

    Route::get('/pending-actions', [PendingActionController::class, 'index'])->name('api.pending-actions.index');
    Route::post('/pending-actions/{pendingAction}/confirm', [PendingActionController::class, 'confirm'])->name('api.pending-actions.confirm')->whereUuid('pendingAction');
    Route::post('/pending-actions/{pendingAction}/cancel', [PendingActionController::class, 'cancel'])->name('api.pending-actions.cancel')->whereUuid('pendingAction');

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

Route::prefix('token')->middleware(['auth:sanctum', 'locale.api', 'user.active', 'ability:mcp', 'token.uploads', 'throttle:token-uploads'])->group(function (): void {
    Route::post('/invoice-payments/preview', [TokenUploadController::class, 'paymentsPreview'])->name('token.payments.preview');
    Route::post('/invoice-payments', [TokenUploadController::class, 'paymentsImport'])->middleware('token.uploads:write')->name('token.payments.import');
    Route::post('/expenses/receipt', [TokenUploadController::class, 'expenseReceipt'])->middleware('token.uploads:write')->name('token.expenses.receipt');
    Route::post('/contracts', [TokenUploadController::class, 'contractStore'])->middleware('token.uploads:write')->name('token.contracts.store');
    Route::post('/contracts/attachments', [TokenUploadController::class, 'contractAttachmentStore'])->middleware('token.uploads:write')->name('token.contracts.attachments.store');
});
