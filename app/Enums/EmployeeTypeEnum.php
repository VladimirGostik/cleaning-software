<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum EmployeeTypeEnum: string
{
    case Brigade = 'brigade';
    case Tpp = 'tpp';
    case SelfEmployed = 'self_employed';

    public function label(): string
    {
        return __('app.employee_type_'.$this->value);
    }
}
