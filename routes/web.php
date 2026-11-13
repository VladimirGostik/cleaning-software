<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AssistantConversationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractFileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\EInvoiceController;
use App\Http\Controllers\EmployeeAccessController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceBulkController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceExportController;
use App\Http\Controllers\InvoicePaymentImportController;
use App\Http\Controllers\InvoiceSettingsController;
use App\Http\Controllers\JobPhotoController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NewPasswordController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\ObjectController;
use App\Http\Controllers\ObjectVisitItemController;
use App\Http\Controllers\PasswordResetLinkController;
use App\Http\Controllers\PendingActionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileMcpTokenController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuotePreviewController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScheduledJobController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskRunController;
use App\Http\Controllers\TemporaryUploadController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkRecordController;
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
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:web-login');
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-email')->name('password.email');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.store');
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
        Route::post('/profile/mcp-tokens', [ProfileMcpTokenController::class, 'store'])->name('profile.mcp-tokens.store');
    });
    Route::delete('/profile/mcp-tokens/{token}', [ProfileMcpTokenController::class, 'destroy'])->name('profile.mcp-tokens.destroy')->whereNumber('token');

    Route::post('/assistant/messages', [AssistantController::class, 'store'])->middleware('throttle:assistant')->name('assistant.messages');
    Route::middleware('throttle:assistant-history')->group(function (): void {
        Route::get('/assistant/conversations', [AssistantConversationController::class, 'index'])->name('assistant.conversations.index');
        Route::get('/assistant/conversations/{conversation}', [AssistantConversationController::class, 'show'])->name('assistant.conversations.show')->whereUuid('conversation');
        Route::patch('/assistant/conversations/{conversation}', [AssistantConversationController::class, 'update'])->name('assistant.conversations.update')->whereUuid('conversation');
        Route::delete('/assistant/conversations/{conversation}', [AssistantConversationController::class, 'destroy'])->name('assistant.conversations.destroy')->whereUuid('conversation');
    });
    Route::get('/pending-actions', [PendingActionController::class, 'index'])->name('pending-actions.index');
    Route::post('/pending-actions/{pendingAction}/confirm', [PendingActionController::class, 'confirm'])->name('pending-actions.confirm')->whereUuid('pendingAction');
    Route::post('/pending-actions/{pendingAction}/cancel', [PendingActionController::class, 'cancel'])->name('pending-actions.cancel')->whereUuid('pendingAction');

    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
    });
    Route::post('/tenants/{tenant}/switch', [TenantController::class, 'switch'])
        ->whereUuid('tenant')
        ->name('tenants.switch');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/{activity}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    Route::post('/audit-logs/{activity}/revert', [AuditLogController::class, 'revert'])->name('audit-logs.revert');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/bell', [NotificationController::class, 'bell'])->name('notifications.bell');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read')->whereUuid('notification');
    Route::get('/settings/notifications', [NotificationSettingsController::class, 'show'])->name('settings.notifications');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::put('/settings/notifications', [NotificationSettingsController::class, 'update'])->name('settings.notifications.update');
    });

    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show')->whereNumber('media');

    Route::post('/uploads', [TemporaryUploadController::class, 'store'])->name('uploads.store');
    Route::delete('/uploads/{uuid}', [TemporaryUploadController::class, 'destroy'])->name('uploads.destroy');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show')->whereUuid('employee');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit')->whereUuid('employee');
    Route::post('/employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate')->whereUuid('employee');
    Route::post('/employees/{employee}/reactivate', [EmployeeController::class, 'reactivate'])->name('employees.reactivate')->whereUuid('employee');
    Route::delete('/employees/{employee}/access', [EmployeeAccessController::class, 'destroy'])->name('employees.access.destroy')->whereUuid('employee');
    Route::post('/employees/{employee}/access/invitation', [EmployeeAccessController::class, 'invitation'])->name('employees.access.invitation')->whereUuid('employee');
    Route::post('/employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store')->whereUuid('employee');
    Route::get('/employees/{employee}/documents/{document}/download', [EmployeeDocumentController::class, 'download'])->name('employees.documents.download')->whereUuid(['employee', 'document'])->scopeBindings();
    Route::delete('/employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employees.documents.destroy')->whereUuid(['employee', 'document'])->scopeBindings();
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::match(['PUT', 'PATCH'], '/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update')->whereUuid('employee');
        Route::post('/employees/{employee}/role', [EmployeeController::class, 'role'])->name('employees.role')->whereUuid('employee');
        Route::post('/employees/{employee}/access', [EmployeeAccessController::class, 'store'])->name('employees.access.store')->whereUuid('employee');
    });

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
        Route::put('/objects/{object}/visit-items', [ObjectVisitItemController::class, 'update'])->name('objects.visit-items.update')->whereUuid('object');
        Route::post('/objects/{object}/work-records', [WorkRecordController::class, 'store'])->name('objects.work-records.store')->whereUuid('object');
    });

    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
    Route::get('/quotes/buyer-preview', [QuoteController::class, 'buyerPreview'])->name('quotes.buyer-preview');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show')->whereUuid('quote');
    Route::get('/quotes/{quote}/edit', [QuoteController::class, 'edit'])->name('quotes.edit')->whereUuid('quote');
    Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy'])->name('quotes.destroy')->whereUuid('quote');
    Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send')->whereUuid('quote');
    Route::post('/quotes/{quote}/accept', [QuoteController::class, 'accept'])->name('quotes.accept')->whereUuid('quote');
    Route::post('/quotes/{quote}/reject', [QuoteController::class, 'reject'])->name('quotes.reject')->whereUuid('quote');
    Route::post('/quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->name('quotes.duplicate')->whereUuid('quote');
    Route::post('/quotes/{quote}/convert-to-invoice', [QuoteController::class, 'convertToInvoice'])->name('quotes.convert-to-invoice')->whereUuid('quote');
    Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf')->whereUuid('quote');
    Route::middleware(['throttle:document-preview'])->group(function (): void {
        Route::get('/quotes/{quote}/preview', [QuoteController::class, 'preview'])->name('quotes.preview')->whereUuid('quote');
        Route::post('/quotes/preview-pdf', [QuotePreviewController::class, 'pdf'])->name('quotes.preview-pdf');
        Route::post('/quotes/{quote}/preview-pdf', [QuotePreviewController::class, 'pdfFor'])->name('quotes.preview-pdf.existing')->whereUuid('quote');
        Route::post('/quotes/text-preview', [QuotePreviewController::class, 'text'])->name('quotes.text-preview');
        Route::post('/quotes/{quote}/text-preview', [QuotePreviewController::class, 'textFor'])->name('quotes.text-preview.existing')->whereUuid('quote');
    });
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
        Route::match(['PUT', 'PATCH'], '/quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update')->whereUuid('quote');
        Route::post('/quotes/{quote}/attach-client', [QuoteController::class, 'attachClient'])->name('quotes.attach-client')->whereUuid('quote');
        Route::post('/quotes/{quote}/convert-to-contract', [QuoteController::class, 'convertToContract'])->name('quotes.convert-to-contract')->whereUuid('quote');
    });

    Route::get('/settings/document-templates', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
    Route::get('/settings/document-templates/create', [DocumentTemplateController::class, 'create'])->name('document-templates.create');
    Route::get('/settings/document-templates/{documentTemplate}/edit', [DocumentTemplateController::class, 'edit'])->name('document-templates.edit')->whereUuid('documentTemplate');
    Route::delete('/settings/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'destroy'])->name('document-templates.destroy')->whereUuid('documentTemplate');
    Route::post('/settings/document-templates/{documentTemplate}/customize', [DocumentTemplateController::class, 'customize'])->name('document-templates.customize')->whereUuid('documentTemplate');
    Route::post('/settings/document-templates/{documentTemplate}/reset', [DocumentTemplateController::class, 'reset'])->name('document-templates.reset')->whereUuid('documentTemplate');
    Route::post('/settings/document-templates/{documentTemplate}/acknowledge', [DocumentTemplateController::class, 'acknowledge'])->name('document-templates.acknowledge')->whereUuid('documentTemplate');
    Route::middleware(['throttle:document-preview'])->group(function (): void {
        Route::post('/settings/document-templates/preview', [DocumentTemplateController::class, 'preview'])->name('document-templates.preview');
        Route::post('/settings/document-templates/preview-pdf', [DocumentTemplateController::class, 'previewPdf'])->name('document-templates.preview-pdf');
    });
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/settings/document-templates', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
        Route::post('/settings/document-templates/global', [DocumentTemplateController::class, 'storeGlobal'])->name('document-templates.store-global');
        Route::match(['PUT', 'PATCH'], '/settings/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'update'])->name('document-templates.update')->whereUuid('documentTemplate');
    });

    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show')->whereUuid('contract');
    Route::get('/contracts/{contract}/edit', [ContractController::class, 'edit'])->name('contracts.edit')->whereUuid('contract');
    Route::delete('/contracts/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy')->whereUuid('contract');
    Route::post('/contracts/{contract}/sign', [ContractController::class, 'sign'])->name('contracts.sign')->whereUuid('contract');
    Route::get('/contracts/{contract}/pdf', [ContractController::class, 'pdf'])->name('contracts.pdf')->whereUuid('contract');
    Route::get('/contracts/{contract}/files/{attachment}', [ContractFileController::class, 'show'])->name('contracts.files.show')->whereUuid(['contract', 'attachment']);
    Route::post('/contracts/{contract}/attachments', [ContractFileController::class, 'storeAttachment'])->name('contracts.attachments.store')->whereUuid('contract');
    Route::delete('/contracts/{contract}/attachments/{attachment}', [ContractFileController::class, 'destroyAttachment'])->name('contracts.attachments.destroy')->whereUuid(['contract', 'attachment']);
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/contracts/from-file', [ContractFileController::class, 'store'])->name('contracts.store-file');
        Route::put('/contracts/{contract}/file-details', [ContractFileController::class, 'update'])->name('contracts.update-file')->whereUuid('contract');
        Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
        Route::post('/contracts/generate', [ContractController::class, 'generate'])->name('contracts.generate')->middleware('throttle:document-preview');
        Route::post('/contracts/generate-pdf', [ContractController::class, 'generatePdf'])->name('contracts.generate-pdf')->middleware('throttle:document-preview');
        Route::match(['PUT', 'PATCH'], '/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update')->whereUuid('contract');
        Route::post('/contracts/{contract}/terminate', [ContractController::class, 'terminate'])->name('contracts.terminate')->whereUuid('contract');
    });

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/export/pohoda', [InvoiceExportController::class, 'pohoda'])->name('invoices.export.pohoda');
    Route::get('/invoices/export/pohoda/preview', [InvoiceExportController::class, 'pohodaPreview'])->name('invoices.export.pohoda.preview');
    Route::get('/invoices/bulk/ids', [InvoiceBulkController::class, 'ids'])->name('invoices.bulk.ids');
    Route::get('/invoices/bulk/pdf', [InvoiceBulkController::class, 'pdf'])->name('invoices.bulk.pdf');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/invoices/bulk/pay', [InvoiceBulkController::class, 'pay'])->name('invoices.bulk.pay');
        Route::post('/invoices/bulk/send', [InvoiceBulkController::class, 'send'])->name('invoices.bulk.send');
        Route::post('/invoices/bulk/issue', [InvoiceBulkController::class, 'issue'])->name('invoices.bulk.issue');
    });
    Route::get('/invoices/payment-import', [InvoicePaymentImportController::class, 'page'])->name('invoices.payment-import');
    Route::get('/invoices/payment-import/invoices', [InvoicePaymentImportController::class, 'invoices'])->name('invoices.payment-import.invoices')->middleware('throttle:payment-import-search');
    Route::middleware('throttle:payment-import')->group(function (): void {
        Route::post('/invoices/payment-import/preview', [InvoicePaymentImportController::class, 'preview'])->name('invoices.payment-import.preview');
        Route::post('/invoices/payment-import', [InvoicePaymentImportController::class, 'store'])->name('invoices.payment-import.store');
    });
    Route::get('/invoices/buyer-preview', [InvoiceController::class, 'buyerPreview'])->name('invoices.buyer-preview');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show')->whereUuid('invoice');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit')->whereUuid('invoice');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy')->whereUuid('invoice');
    Route::delete('/invoices/{invoice}/payments/{payment}', [InvoiceController::class, 'destroyPayment'])->name('invoices.payments.destroy')->whereUuid(['invoice', 'payment'])->scopeBindings();
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel')->whereUuid('invoice');
    Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate')->whereUuid('invoice');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send')->whereUuid('invoice');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf')->whereUuid('invoice');
    Route::get('/invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview')->whereUuid('invoice')->middleware('throttle:document-preview');
    Route::get('/invoices/{invoice}/efa-xml', [EInvoiceController::class, 'xml'])->name('invoices.efa.xml')->whereUuid('invoice');
    Route::post('/invoices/{invoice}/efa-submit', [EInvoiceController::class, 'submit'])->name('invoices.efa.submit')->whereUuid('invoice');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::match(['PUT', 'PATCH'], '/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update')->whereUuid('invoice');
        Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'recordPayment'])->name('invoices.pay')->whereUuid('invoice');
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
    Route::get('/settings/invoicing/preview', [InvoiceSettingsController::class, 'preview'])->name('settings.invoicing.preview');
    Route::get('/settings/invoicing/signature', [InvoiceSettingsController::class, 'signature'])->name('settings.invoicing.signature');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::put('/settings/invoicing', [InvoiceSettingsController::class, 'update'])->name('settings.invoicing.update');
    });

    Route::get('/jobs', [ScheduledJobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/calendar', [ScheduledJobController::class, 'calendar'])->name('jobs.calendar');
    Route::get('/jobs/create', [ScheduledJobController::class, 'create'])->name('jobs.create');
    Route::get('/jobs/{job}', [ScheduledJobController::class, 'show'])->name('jobs.show')->whereUuid('job');
    Route::get('/jobs/{job}/edit', [ScheduledJobController::class, 'edit'])->name('jobs.edit')->whereUuid('job');
    Route::post('/jobs/{job}/cancel', [ScheduledJobController::class, 'cancel'])->name('jobs.cancel')->whereUuid('job');
    Route::post('/jobs/{job}/start', [ScheduledJobController::class, 'start'])->name('jobs.start')->whereUuid('job');
    Route::post('/jobs/{job}/complete', [ScheduledJobController::class, 'complete'])->name('jobs.complete')->whereUuid('job');
    Route::post('/jobs/{job}/unapprove', [ScheduledJobController::class, 'unapprove'])->name('jobs.unapprove')->whereUuid('job');
    Route::get('/jobs/{job}/photos/{photo}', [JobPhotoController::class, 'show'])->name('jobs.photos.show')->whereUuid(['job', 'photo']);
    Route::delete('/jobs/{job}/work-record', [WorkRecordController::class, 'destroy'])->name('jobs.work-record.destroy')->whereUuid('job');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/jobs', [ScheduledJobController::class, 'store'])->name('jobs.store');
        Route::match(['PUT', 'PATCH'], '/jobs/{job}', [ScheduledJobController::class, 'update'])->name('jobs.update')->whereUuid('job');
        Route::post('/jobs/{job}/assign', [ScheduledJobController::class, 'assign'])->name('jobs.assign')->whereUuid('job');
        Route::put('/jobs/{job}/work-record', [WorkRecordController::class, 'update'])->name('jobs.work-record.update')->whereUuid('job');
    });

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show')->whereUuid('task');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy')->whereUuid('task');
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete')->whereUuid('task');
    Route::post('/tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen')->whereUuid('task');
    Route::post('/tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status')->whereUuid('task');
    Route::get('/tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'show'])->name('tasks.attachments.show')->whereUuid(['task', 'attachment']);
    Route::delete('/tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('tasks.attachments.destroy')->whereUuid(['task', 'attachment']);
    Route::post('/tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->middleware('throttle:task-attachments')->name('tasks.attachments.store')->whereUuid('task');
    Route::get('/tasks/{task}/thread', [TaskCommentController::class, 'thread'])->name('tasks.thread')->whereUuid('task');
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->middleware('throttle:task-comments')->name('tasks.comments.store')->whereUuid('task');
    Route::put('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'update'])->middleware('throttle:task-comments')->name('tasks.comments.update')->whereUuid(['task', 'comment']);
    Route::delete('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy')->whereUuid(['task', 'comment']);
    Route::get('/tasks/{task}/ai-run-status', [TaskRunController::class, 'status'])->name('tasks.ai-run-status')->whereUuid('task');
    Route::post('/tasks/{task}/ai-runs', [TaskRunController::class, 'store'])->middleware('throttle:task-ai-runs')->name('tasks.ai-runs.store')->whereUuid('task');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update')->whereUuid('task');
        Route::patch('/tasks/{task}', [TaskController::class, 'patch'])->name('tasks.patch')->whereUuid('task');
        Route::post('/tasks/{task}/assign', [TaskController::class, 'assign'])->name('tasks.assign')->whereUuid('task');
    });

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/import', [ExpenseController::class, 'importPage'])->name('expenses.import');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show')->whereUuid('expense');
    Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->name('expenses.receipt')->whereUuid('expense');
    Route::post('/expenses/{expense}/receipt', [ExpenseController::class, 'attachReceipt'])->name('expenses.receipt.store')->whereUuid('expense');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy')->whereUuid('expense');
    Route::middleware('throttle:ai-analyze')->group(function (): void {
        Route::post('/expenses/analyze', [ExpenseController::class, 'analyze'])->name('expenses.analyze');
        Route::post('/expenses/import/preview', [ExpenseController::class, 'importPreview'])->name('expenses.import.preview');
    });
    Route::post('/expenses/import', [ExpenseController::class, 'import'])->name('expenses.import.store');
    Route::middleware([HandlePrecognitiveRequests::class])->group(function (): void {
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('/expenses/batch', [ExpenseController::class, 'batchStore'])->name('expenses.batch.store');
        Route::match(['PUT', 'PATCH'], '/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update')->whereUuid('expense');
        Route::post('/expenses/{expense}/confirm', [ExpenseController::class, 'confirm'])->name('expenses.confirm')->whereUuid('expense');
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
