<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ExpenseImportAccountStatusEnum: string
{
    case Matched = 'matched';
    case OtherCompany = 'other_company';
    case UnknownAccount = 'unknown_account';
    case CompanyIbanMissing = 'company_iban_missing';
}
