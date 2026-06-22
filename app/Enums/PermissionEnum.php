<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PermissionEnum: string
{
    // Clients
    case ViewClients = 'view clients';
    case CreateClients = 'create clients';
    case EditClients = 'edit clients';
    case DeleteClients = 'delete clients';

    // Objects
    case ViewObjects = 'view objects';
    case CreateObjects = 'create objects';
    case EditObjects = 'edit objects';
    case DeleteObjects = 'delete objects';

    // Quotes
    case ViewQuotes = 'view quotes';
    case CreateQuotes = 'create quotes';
    case EditQuotes = 'edit quotes';
    case SendQuotes = 'send quotes';
    case ApproveQuotes = 'approve quotes';
    case DeleteQuotes = 'delete quotes';

    // Contracts
    case ViewContracts = 'view contracts';
    case CreateContracts = 'create contracts';
    case EditContracts = 'edit contracts';
    case TerminateContracts = 'terminate contracts';
    case DeleteContracts = 'delete contracts';

    // Contract Templates
    case ViewContractTemplates = 'view contract_templates';
    case CreateContractTemplates = 'create contract_templates';
    case EditContractTemplates = 'edit contract_templates';
    case DeleteContractTemplates = 'delete contract_templates';

    // Employees
    case ViewEmployees = 'view employees';
    case CreateEmployees = 'create employees';
    case EditEmployees = 'edit employees';
    case AssignEmployees = 'assign employees';
    case DeleteEmployees = 'delete employees';

    // Schedule
    case ViewSchedule = 'view schedule';
    case CreateSchedule = 'create schedule';
    case EditSchedule = 'edit schedule';
    case AssignCleaners = 'assign cleaners';

    // Invoices
    case ViewInvoices = 'view invoices';
    case CreateInvoices = 'create invoices';
    case EditInvoices = 'edit invoices';
    case CancelInvoices = 'cancel invoices';

    // Recurring Invoices
    case ViewRecurringInvoices = 'view recurring_invoices';
    case CreateRecurringInvoices = 'create recurring_invoices';
    case EditRecurringInvoices = 'edit recurring_invoices';
    case DeleteRecurringInvoices = 'delete recurring_invoices';

    // Templates
    case ViewTemplates = 'view templates';
    case UploadTemplates = 'upload templates';
    case DeleteTemplates = 'delete templates';

    // Complaints
    case ViewComplaints = 'view complaints';
    case ResolveComplaints = 'resolve complaints';
    case RejectComplaints = 'reject complaints';

    // Photos
    case ViewPhotos = 'view photos';
    case ReviewPhotos = 'review photos';

    // Notifications
    case ViewNotifications = 'view notifications';
    case ConfigureNotifications = 'configure notifications';

    // Permissions
    case ManageRoles = 'manage roles';

    // Settings
    case ManageTenant = 'manage tenant';
    case ManageBillingSettings = 'manage billing settings';
    case ManageSubscription = 'manage subscription';

    // Tenants
    case ViewTenants = 'view tenants';
    case CreateTenants = 'create tenants';
    case EditTenants = 'edit tenants';

    // Audit
    case ViewAuditLogs = 'view audit logs';

    /**
     * @return array<int, string>
     */
    public function label(): string
    {
        return __('app.permission.' . $this->value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
