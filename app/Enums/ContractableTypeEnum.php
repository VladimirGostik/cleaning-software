<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\CleaningObject;
use App\Models\TenantMembership;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Values match `Relation::morphMap` aliases registered in `AppServiceProvider::boot()`.
 */
#[TypeScript]
enum ContractableTypeEnum: string
{
    case CleaningObject = 'cleaning_object';
    case TenantMembership = 'tenant_membership';

    public function label(): string
    {
        return __('app.contractable_type_'.$this->value);
    }

    /** @return class-string<CleaningObject|TenantMembership> */
    public function modelClass(): string
    {
        return match ($this) {
            self::CleaningObject => CleaningObject::class,
            self::TenantMembership => TenantMembership::class,
        };
    }

    public function table(): string
    {
        return match ($this) {
            self::CleaningObject => 'objects',
            self::TenantMembership => 'tenant_memberships',
        };
    }
}
