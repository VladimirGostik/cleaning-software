<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DashboardAlertTypeEnum: string
{
    case OverdueInvoice = 'overdue_invoice';
    case SupplierIncomplete = 'supplier_incomplete';
    case UnassignedJob = 'unassigned_job';
    case ContractExpiring = 'contract_expiring';
    case QuoteExpiring = 'quote_expiring';

    public function label(): string
    {
        return __('app.dashboard_alert_'.$this->value);
    }

    /** Urgency ordering within the merged alert feed — lower renders first. */
    public function rank(): int
    {
        return match ($this) {
            self::OverdueInvoice => 1,
            self::SupplierIncomplete => 2,
            self::UnassignedJob => 3,
            self::ContractExpiring => 4,
            self::QuoteExpiring => 5,
        };
    }
}
