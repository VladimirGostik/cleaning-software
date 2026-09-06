<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum JobTypeEnum: string
{
    case Regular = 'regular';
    case OneOff = 'one_off';
    case Special = 'special';

    public function label(): string
    {
        return __('app.job_type_'.$this->value);
    }
}
