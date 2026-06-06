<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum FeatureEnum: string
{
    case Clients = 'clients';
    case Objects = 'objects';
    case Quotes = 'quotes';
    case Contracts = 'contracts';
    case Schedule = 'schedule';
    case Invoices = 'invoices';
    case Employees = 'employees';
    case Reports = 'reports';
    case MobileAccess = 'mobile_access';
    case MultiUser = 'multi_user';

    public function label(): string
    {
        return __('app.feature.' . $this->value);
    }
}
