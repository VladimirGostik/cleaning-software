<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceSettingsController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NewPasswordController;
use App\Http\Controllers\ObjectController;
use App\Http\Controllers\PasswordResetLinkController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TemporaryUploadController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');

    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
    });
});

// Guest-accessible — the invitation token is the credential. Outside `auth`/`tenant.required`
// so a user whose last membership was deactivated can still accept a new invitation (D4).
Route::get('/invitations/{token}', [InvitationController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('invitations.show');
Route::middleware([HandlePrecognitiveRequests::class, 'throttle:invitation-accept'])->group(function (): void {
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('invitations.accept');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'tenant.required'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');
    });

    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
    });
    Route::post('/tenants/{tenant}/switch', [TenantController::class, 'switch'])
        ->whereUuid('tenant')
        ->name('tenants.switch');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/{activity}', [AuditLogController::class, 'show'])->name('audit-logs.show');

    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show')->whereNumber('media');

    Route::post('/uploads', [TemporaryUploadController::class, 'store'])->name('uploads.store');
    Route::delete('/uploads/{uuid}', [TemporaryUploadController::class, 'destroy'])->name('uploads.destroy');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/autocomplete', [UserController::class, 'autocomplete'])->name('users.autocomplete');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show')->whereUuid('client');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy')->whereUuid('client');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::match(['PUT', 'PATCH'], '/clients/{client}', [ClientController::class, 'update'])->name('clients.update')->whereUuid('client');
    });

    Route::get('/objects', [ObjectController::class, 'index'])->name('objects.index');
    Route::get('/objects/{object}', [ObjectController::class, 'show'])->name('objects.show')->whereUuid('object');
    Route::post('/objects/{object}/deactivate', [ObjectController::class, 'deactivate'])->name('objects.deactivate')->whereUuid('object');
    Route::post('/objects/{object}/reactivate', [ObjectController::class, 'reactivate'])->name('objects.reactivate')->whereUuid('object');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/objects', [ObjectController::class, 'store'])->name('objects.store');
        Route::match(['PUT', 'PATCH'], '/objects/{object}', [ObjectController::class, 'update'])->name('objects.update')->whereUuid('object');
    });

    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show')->whereUuid('quote');
    Route::get('/quotes/{quote}/edit', [QuoteController::class, 'edit'])->name('quotes.edit')->whereUuid('quote');
    Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy'])->name('quotes.destroy')->whereUuid('quote');
    Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send')->whereUuid('quote');
    Route::post('/quotes/{quote}/accept', [QuoteController::class, 'accept'])->name('quotes.accept')->whereUuid('quote');
    Route::post('/quotes/{quote}/reject', [QuoteController::class, 'reject'])->name('quotes.reject')->whereUuid('quote');
    Route::post('/quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->name('quotes.duplicate')->whereUuid('quote');
    Route::post('/quotes/{quote}/convert-to-invoice', [QuoteController::class, 'convertToInvoice'])->name('quotes.convert-to-invoice')->whereUuid('quote');
    Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf')->whereUuid('quote');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
        Route::match(['PUT', 'PATCH'], '/quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update')->whereUuid('quote');
        Route::post('/quotes/{quote}/attach-client', [QuoteController::class, 'attachClient'])->name('quotes.attach-client')->whereUuid('quote');
        Route::post('/quotes/{quote}/convert-to-contract', [QuoteController::class, 'convertToContract'])->name('quotes.convert-to-contract')->whereUuid('quote');
    });

    Route::get('/contract-templates', [ContractTemplateController::class, 'index'])->name('contract-templates.index');
    Route::get('/contract-templates/create', [ContractTemplateController::class, 'create'])->name('contract-templates.create');
    Route::get('/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'show'])->name('contract-templates.show')->whereUuid('contractTemplate');
    Route::get('/contract-templates/{contractTemplate}/edit', [ContractTemplateController::class, 'edit'])->name('contract-templates.edit')->whereUuid('contractTemplate');
    Route::delete('/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'destroy'])->name('contract-templates.destroy')->whereUuid('contractTemplate');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/contract-templates', [ContractTemplateController::class, 'store'])->name('contract-templates.store');
        Route::match(['PUT', 'PATCH'], '/contract-templates/{contractTemplate}', [ContractTemplateController::class, 'update'])->name('contract-templates.update')->whereUuid('contractTemplate');
    });

    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show')->whereUuid('contract');
    Route::get('/contracts/{contract}/edit', [ContractController::class, 'edit'])->name('contracts.edit')->whereUuid('contract');
    Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy')->whereUuid('contract');
    Route::post('/contracts/{contract}/sign', [ContractController::class, 'sign'])->name('contracts.sign')->whereUuid('contract');
    Route::get('/contracts/{contract}/pdf', [ContractController::class, 'pdf'])->name('contracts.pdf')->whereUuid('contract');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
        Route::match(['PUT', 'PATCH'], '/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update')->whereUuid('contract');
        Route::post('/contracts/{contract}/terminate', [ContractController::class, 'terminate'])->name('contracts.terminate')->whereUuid('contract');
    });

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show')->whereUuid('invoice');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit')->whereUuid('invoice');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy')->whereUuid('invoice');
    Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay')->whereUuid('invoice');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel')->whereUuid('invoice');
    Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate')->whereUuid('invoice');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send')->whereUuid('invoice');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf')->whereUuid('invoice');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::match(['PUT', 'PATCH'], '/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update')->whereUuid('invoice');
        Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue')->whereUuid('invoice');
    });

    Route::get('/recurring-invoices', [RecurringInvoiceController::class, 'index'])->name('recurring-invoices.index');
    Route::get('/recurring-invoices/create', [RecurringInvoiceController::class, 'create'])->name('recurring-invoices.create');
    Route::get('/recurring-invoices/{recurringInvoice}', [RecurringInvoiceController::class, 'show'])->name('recurring-invoices.show')->whereUuid('recurringInvoice');
    Route::get('/recurring-invoices/{recurringInvoice}/edit', [RecurringInvoiceController::class, 'edit'])->name('recurring-invoices.edit')->whereUuid('recurringInvoice');
    Route::delete('/recurring-invoices/{recurringInvoice}', [RecurringInvoiceController::class, 'destroy'])->name('recurring-invoices.destroy')->whereUuid('recurringInvoice');
    Route::post('/recurring-invoices/{recurringInvoice}/pause', [RecurringInvoiceController::class, 'pause'])->name('recurring-invoices.pause')->whereUuid('recurringInvoice');
    Route::post('/recurring-invoices/{recurringInvoice}/resume', [RecurringInvoiceController::class, 'resume'])->name('recurring-invoices.resume')->whereUuid('recurringInvoice');
    Route::post('/recurring-invoices/{recurringInvoice}/cancel', [RecurringInvoiceController::class, 'cancel'])->name('recurring-invoices.cancel')->whereUuid('recurringInvoice');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/recurring-invoices', [RecurringInvoiceController::class, 'store'])->name('recurring-invoices.store');
        Route::match(['PUT', 'PATCH'], '/recurring-invoices/{recurringInvoice}', [RecurringInvoiceController::class, 'update'])->name('recurring-invoices.update')->whereUuid('recurringInvoice');
    });

    Route::get('/settings/invoicing', [InvoiceSettingsController::class, 'show'])->name('settings.invoicing');
    Route::get('/settings/invoicing/preview/{template}', [InvoiceSettingsController::class, 'preview'])->name('settings.invoicing.preview');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::put('/settings/invoicing', [InvoiceSettingsController::class, 'update'])->name('settings.invoicing.update');
    });

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    // API docs — registered here (not via Scribe's add_routes) so that the web middleware group
    // (sessions, cookies) is active. Without it, auth middleware cannot read the session.
    Route::middleware('permission:'.PermissionEnum::ViewApiDocs->value)->group(function (): void {
        Route::view('/docs', 'scribe.index')->name('scribe');

        Route::get('/docs.postman', function (): JsonResponse {
            return new JsonResponse(Storage::disk('local')->get('scribe/collection.json'), json: true);
        })->name('scribe.postman');

        Route::get('/docs.openapi', function () {
            return response()->file(Storage::disk('local')->path('scribe/openapi.yaml'));
        })->name('scribe.openapi');
    });
});
