<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class JobAssignData extends Data
{
    public function __construct(
        #[Nullable, Uuid]
        public readonly ?string $assigned_membership_id = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        /** @var string|null $tenantId */
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return [
            'assigned_membership_id' => [
                Rule::exists('tenant_memberships', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true),
            ],
        ];
    }
}
