<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ExpenseCategoryEnum: string
{
    case Fuel = 'fuel';
    case Materials = 'materials';
    case CleaningSupplies = 'cleaning_supplies';
    case Equipment = 'equipment';
    case Services = 'services';
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Wages = 'wages';
    case Insurance = 'insurance';
    case Fees = 'fees';
    case BankCharges = 'bank_charges';
    case Other = 'other';

    public function label(): string
    {
        return __('app.expense_category_'.$this->value);
    }
}
