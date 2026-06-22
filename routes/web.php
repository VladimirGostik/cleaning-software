<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceSettingsController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ObjectController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\TenantController;
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

    Route::get('register', [RegisterController::class, 'showRegister'])->name('register');
    Route::post('register', [RegisterController::class, 'register'])->middleware('throttle:register');

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

    // Objects — feature-gated (plan axis: objects feature must be enabled).
    Route::middleware('feature:objects')->group(function (): void {
        Route::get('/objects', [ObjectController::class, 'index'])->name('objects.index');
        Route::post('/objects', [ObjectController::class, 'store'])->name('objects.store');
        Route::get('/objects/{object}', [ObjectController::class, 'show'])->name('objects.show')->whereUuid('object');
        Route::match(['PUT', 'PATCH'], '/objects/{object}', [ObjectController::class, 'update'])->name('objects.update')->whereUuid('object');
        Route::delete('/objects/{object}', [ObjectController::class, 'destroy'])->name('objects.destroy')->whereUuid('object');
    });

    // Quotes — feature-gated (plan axis: quotes feature must be enabled).
    Route::middleware('feature:quotes')->group(function (): void {
        Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('/quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
        Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
        Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show')->whereUuid('quote');
        Route::get('/quotes/{quote}/edit', [QuoteController::class, 'edit'])->name('quotes.edit')->whereUuid('quote');
        Route::match(['PUT', 'PATCH'], '/quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update')->whereUuid('quote');
        Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy'])->name('quotes.destroy')->whereUuid('quote');
        Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send')->whereUuid('quote');
        Route::post('/quotes/{quote}/accept', [QuoteController::class, 'accept'])->name('quotes.accept')->whereUuid('quote');
        Route::post('/quotes/{quote}/reject', [QuoteController::class, 'reject'])->name('quotes.reject')->whereUuid('quote');
        Route::post('/quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->name('quotes.duplicate')->whereUuid('quote');
        Route::post('/quotes/{quote}/convert-invoice', [QuoteController::class, 'convertToInvoice'])->name('quotes.convert-invoice')->whereUuid('quote');
        Route::post('/quotes/{quote}/convert-contract', [QuoteController::class, 'convertToContract'])->name('quotes.convert-contract')->whereUuid('quote');
        Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf')->whereUuid('quote');
    });

    // Invoices — feature-gated (plan axis: invoices feature must be enabled).
    Route::middleware('feature:invoices')->group(function (): void {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        // Static segments must precede the {invoice} wildcard to avoid route collision.
        Route::get('/invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
        Route::post('/invoices/bulk', [InvoiceController::class, 'bulk'])->name('invoices.bulk');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show')->whereUuid('invoice');
        Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit')->whereUuid('invoice');
        Route::match(['PUT', 'PATCH'], '/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update')->whereUuid('invoice');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy')->whereUuid('invoice');
        Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue')->whereUuid('invoice');
        Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay')->whereUuid('invoice');
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel')->whereUuid('invoice');
        Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate')->whereUuid('invoice');
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf')->whereUuid('invoice');
        Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send')->whereUuid('invoice');

        // Recurring invoices — static segments before {recurringInvoice} wildcard.
        Route::get('/recurring-invoices', [RecurringInvoiceController::class, 'index'])->name('recurring-invoices.index');
        Route::get('/recurring-invoices/create', [RecurringInvoiceController::class, 'create'])->name('recurring-invoices.create');
        Route::post('/recurring-invoices', [RecurringInvoiceController::class, 'store'])->name('recurring-invoices.store');
        Route::get('/recurring-invoices/{recurringInvoice}', [RecurringInvoiceController::class, 'show'])->name('recurring-invoices.show')->whereUuid('recurringInvoice');
        Route::get('/recurring-invoices/{recurringInvoice}/edit', [RecurringInvoiceController::class, 'edit'])->name('recurring-invoices.edit')->whereUuid('recurringInvoice');
        Route::match(['PUT', 'PATCH'], '/recurring-invoices/{recurringInvoice}', [RecurringInvoiceController::class, 'update'])->name('recurring-invoices.update')->whereUuid('recurringInvoice');
        Route::delete('/recurring-invoices/{recurringInvoice}', [RecurringInvoiceController::class, 'destroy'])->name('recurring-invoices.destroy')->whereUuid('recurringInvoice');
        Route::post('/recurring-invoices/{recurringInvoice}/pause', [RecurringInvoiceController::class, 'pause'])->name('recurring-invoices.pause')->whereUuid('recurringInvoice');
        Route::post('/recurring-invoices/{recurringInvoice}/resume', [RecurringInvoiceController::class, 'resume'])->name('recurring-invoices.resume')->whereUuid('recurringInvoice');
        Route::post('/recurring-invoices/{recurringInvoice}/cancel', [RecurringInvoiceController::class, 'cancel'])->name('recurring-invoices.cancel')->whereUuid('recurringInvoice');

        // Invoice settings — tenant default template, numbering format, IBAN, VAT rate, registration info.
        Route::get('/settings/invoicing', [InvoiceSettingsController::class, 'show'])->name('settings.invoicing');
        Route::put('/settings/invoicing', [InvoiceSettingsController::class, 'update'])->name('settings.invoicing.update');
        Route::get('/settings/invoicing/preview/{template}', [InvoiceSettingsController::class, 'preview'])->name('settings.invoicing.preview');
    });

    // Contracts — feature-gated (plan axis: contracts feature must be enabled).
    Route::middleware('feature:contracts')->group(function (): void {
        // Contract Templates
        Route::get('/contract-templates', [ContractTemplateController::class, 'index'])->name('contract-templates.index');
        Route::get('/contract-templates/create', [ContractTemplateController::class, 'create'])->name('contract-templates.create');
        Route::post('/contract-templates', [ContractTemplateController::class, 'store'])->name('contract-templates.store');
        Route::get('/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'show'])->name('contract-templates.show')->whereUuid('contractTemplate');
        Route::get('/contract-templates/{contractTemplate}/edit', [ContractTemplateController::class, 'edit'])->name('contract-templates.edit')->whereUuid('contractTemplate');
        Route::match(['PUT', 'PATCH'], '/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'update'])->name('contract-templates.update')->whereUuid('contractTemplate');
        Route::delete('/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'destroy'])->name('contract-templates.destroy')->whereUuid('contractTemplate');

        // Contracts — static segments before {contract} wildcard
        Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
        Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
        Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show')->whereUuid('contract');
        Route::get('/contracts/{contract}/edit', [ContractController::class, 'edit'])->name('contracts.edit')->whereUuid('contract');
        Route::match(['PUT', 'PATCH'], '/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update')->whereUuid('contract');
        Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy')->whereUuid('contract');
        Route::post('/contracts/{contract}/sign', [ContractController::class, 'sign'])->name('contracts.sign')->whereUuid('contract');
        Route::post('/contracts/{contract}/terminate', [ContractController::class, 'terminate'])->name('contracts.terminate')->whereUuid('contract');
        Route::get('/contracts/{contract}/pdf', [ContractController::class, 'pdf'])->name('contracts.pdf')->whereUuid('contract');
    });

    // Employees — feature-gated (plan axis: employees feature must be enabled).
    Route::middleware('feature:employees')->group(function (): void {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show')->whereUuid('employee');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit')->whereUuid('employee');
        Route::match(['PUT', 'PATCH'], '/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update')->whereUuid('employee');
        Route::post('/employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate')->whereUuid('employee');
    });

    // Notifications center and settings.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read')->whereUuid('notification');
    Route::get('/settings/notifications', [NotificationController::class, 'settings'])->name('settings.notifications');
    Route::put('/settings/notifications', [NotificationController::class, 'updateSettings'])->name('settings.notifications.update');

    // Tenants — self-service; auth middleware is the only gate (D4a).
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
});

// Invitations — token IS the gate; accessible to guests AND authenticated users (D4a exception).
// GET serves the accept page (shows form or handles same-email auto-accept).
// POST submits the accept form.
Route::get('/invitations/{token}', [InvitationController::class, 'show'])
    ->name('invitations.show')
    ->whereAlphaNumeric('token');

Route::post('/invitations/{token}', [InvitationController::class, 'accept'])
    ->name('invitations.accept')
    ->whereAlphaNumeric('token')
    ->middleware('throttle:invitation-accept');

Route::get('language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');
