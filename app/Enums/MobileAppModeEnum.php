<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Dashboard\TenantVisibility;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum MobileAppModeEnum: string
{
    case Admin = 'admin';
    case Cleaner = 'cleaner';

    /** @param list<TenantVisibility> $mobileVisibilities */
    public static function fromVisibilities(array $mobileVisibilities): self
    {
        $records = array_any(
            $mobileVisibilities,
            static fn (TenantVisibility $v): bool => in_array(PermissionEnum::RecordOwnWork, $v->permissions, true),
        );
        $seesAll = array_any(
            $mobileVisibilities,
            static fn (TenantVisibility $v): bool => in_array(PermissionEnum::ViewAllSchedule, $v->permissions, true),
        );

        return $records && ! $seesAll ? self::Cleaner : self::Admin;
    }
}
