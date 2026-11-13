<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Data\Objects\ObjectVisitItemsSyncData;
use App\Rules\WorkRecordPerformedAt;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class WorkRecordAdminStoreData extends Data
{
    /** @param list<string> $item_ids */
    public function __construct(
        #[Required, Uuid]
        public readonly string $tenant_membership_id,
        #[LiteralTypeScriptType('string[]')]
        public readonly array $item_ids,
        #[Required]
        public readonly string $performed_at,
        #[Nullable, Max(2000)]
        public readonly ?string $note = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        /** @var string|null $tenantId */
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return [
            'tenant_membership_id' => [
                Rule::exists('tenant_memberships', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true),
            ],
            'item_ids' => ['bail', 'required', 'array', 'min:1', 'max:'.ObjectVisitItemsSyncData::MAX_ITEMS],
            'item_ids.*' => ['bail', 'string', 'uuid', 'distinct'],
            'performed_at' => ['bail', 'required', new WorkRecordPerformedAt(null)],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'item_ids.required' => __('app.work_record_items_required'),
            'item_ids.min' => __('app.work_record_items_required'),
            'item_ids.array' => __('app.work_record_item_list_invalid'),
            'item_ids.max' => __('app.work_record_item_list_invalid'),
        ];
    }
}
