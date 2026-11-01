<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum NavIconEnum: string
{
    case Dashboard = 'dashboard';
    case Buildings = 'buildings';
    case Team = 'team';
    case Clients = 'clients';
    case Quotes = 'quotes';
    case Invoices = 'invoices';
    case RecurringInvoices = 'recurring_invoices';
    case Contracts = 'contracts';
    case Documents = 'documents';
    case Schedule = 'schedule';
    case Tasks = 'tasks';
    case Expenses = 'expenses';
    case Notifications = 'notifications';
    case NotificationSettings = 'notification_settings';
    case PendingActions = 'pending_actions';
    case Users = 'users';
    case Roles = 'roles';
    case AuditLogs = 'audit_logs';
    case Media = 'media';
    case Profile = 'profile';
    case InvoiceSettings = 'invoice_settings';
    case Settings = 'settings';
}
