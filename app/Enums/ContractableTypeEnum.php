<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\CleaningObject;
use App\Models\Client;
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
    case Client = 'client';

    public function label(): string
    {
        return __('app.contractable_type_'.$this->value);
    }

    /** @return class-string<CleaningObject|TenantMembership|Client> */
    public function modelClass(): string
    {
        return match ($this) {
            self::CleaningObject => CleaningObject::class,
            self::TenantMembership => TenantMembership::class,
            self::Client => Client::class,
        };
    }

    public function table(): string
    {
        return match ($this) {
            self::CleaningObject => 'objects',
            self::TenantMembership => 'tenant_memberships',
            self::Client => 'clients',
        };
    }
}
