<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PermissionEnum: string
{
    // Platform — employees
    case ViewEmployees = 'view employees';
    case CreateEmployees = 'create employees';
    case EditEmployees = 'edit employees';
    case DeleteEmployees = 'delete employees';
    case AssignEmployees = 'assign employees';

    // Platform — roles
    case ViewRoles = 'view roles';
    case CreateRoles = 'create roles';
    case EditRoles = 'edit roles';
    case DeleteRoles = 'delete roles';

    // Platform — audit / docs / media
    case ViewAuditLogs = 'view audit logs';
    case ViewApiDocs = 'view api docs';
    case ViewMedia = 'view media';
    case UploadFiles = 'upload files';

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
    /** Breadth modifier — requires ViewObjects. Without it the actor sees only objects reachable through her active membership. */
    case ViewAllObjects = 'view all objects';

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

    // Contract templates
    case ViewContractTemplates = 'view contract_templates';
    case CreateContractTemplates = 'create contract_templates';
    case EditContractTemplates = 'edit contract_templates';
    case DeleteContractTemplates = 'delete contract_templates';

    // Schedule
    case ViewSchedule = 'view schedule';
    case CreateSchedule = 'create schedule';
    case EditSchedule = 'edit schedule';
    case AssignCleaners = 'assign cleaners';
    /** Breadth modifier — requires ViewSchedule. Without it the actor sees only jobs reachable through her active membership. */
    case ViewAllSchedule = 'view all schedule';

    // Invoices
    case ViewInvoices = 'view invoices';
    case CreateInvoices = 'create invoices';
    case EditInvoices = 'edit invoices';
    case CancelInvoices = 'cancel invoices';
    case ManageBillingSettings = 'manage billing settings';

    // Recurring invoices
    case ViewRecurringInvoices = 'view recurring_invoices';
    case CreateRecurringInvoices = 'create recurring_invoices';
    case EditRecurringInvoices = 'edit recurring_invoices';
    case DeleteRecurringInvoices = 'delete recurring_invoices';

    // Notifications
    case ViewNotifications = 'view notifications';
    case ConfigureNotifications = 'configure notifications';

    public function label(): string
    {
        return __('app.permission_'.self::key($this->value));
    }

    public function group(): string
    {
        return match ($this) {
            self::ViewEmployees, self::CreateEmployees, self::EditEmployees,
            self::DeleteEmployees, self::AssignEmployees => 'employees',

            self::ViewRoles, self::CreateRoles, self::EditRoles, self::DeleteRoles => 'roles',

            self::ViewAuditLogs => 'audit_logs',
            self::ViewApiDocs => 'api_docs',
            self::ViewMedia, self::UploadFiles => 'media',

            self::ViewClients, self::CreateClients, self::EditClients, self::DeleteClients => 'clients',

            self::ViewObjects, self::CreateObjects, self::EditObjects,
            self::DeleteObjects, self::ViewAllObjects => 'objects',

            self::ViewQuotes, self::CreateQuotes, self::EditQuotes,
            self::SendQuotes, self::ApproveQuotes, self::DeleteQuotes => 'quotes',

            self::ViewContracts, self::CreateContracts, self::EditContracts,
            self::TerminateContracts, self::DeleteContracts => 'contracts',

            self::ViewContractTemplates, self::CreateContractTemplates,
            self::EditContractTemplates, self::DeleteContractTemplates => 'contract_templates',

            self::ViewSchedule, self::CreateSchedule, self::EditSchedule,
            self::AssignCleaners, self::ViewAllSchedule => 'schedule',

            self::ViewInvoices, self::CreateInvoices, self::EditInvoices,
            self::CancelInvoices => 'invoices',

            self::ManageBillingSettings => 'billing',

            self::ViewRecurringInvoices, self::CreateRecurringInvoices,
            self::EditRecurringInvoices, self::DeleteRecurringInvoices => 'recurring_invoices',

            self::ViewNotifications, self::ConfigureNotifications => 'notifications',
        };
    }

    public function groupLabel(): string
    {
        return __('app.permission_group_'.$this->group());
    }

    public function sharedKey(): string
    {
        return Str::camel($this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    private static function key(string $value): string
    {
        return str_replace(' ', '_', $value);
    }
}
