<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Data\Objects\ObjectVisitItemsSyncData;
use App\Rules\WorkRecordPerformedAt;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ConcreteValidator;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class WorkRecordUpdateData extends Data
{
    /**
     * @param  list<string>  $keep_item_ids
     * @param  list<string>  $add_item_ids
     */
    public function __construct(
        #[Required, Uuid]
        public readonly string $tenant_membership_id,
        #[Required]
        public readonly string $performed_at,
        #[LiteralTypeScriptType('string[]')]
        public readonly array $keep_item_ids,
        #[LiteralTypeScriptType('string[]')]
        public readonly array $add_item_ids,
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
                'required',
                'uuid',
                Rule::exists('tenant_memberships', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true),
            ],
            'performed_at' => ['bail', 'required', new WorkRecordPerformedAt(null)],
            'note' => ['nullable', 'string', 'max:2000'],
            'keep_item_ids' => ['bail', 'present', 'array', 'max:'.ObjectVisitItemsSyncData::MAX_ITEMS],
            'keep_item_ids.*' => ['bail', 'string', 'uuid', 'distinct'],
            'add_item_ids' => ['bail', 'present', 'array', 'max:'.ObjectVisitItemsSyncData::MAX_ITEMS],
            'add_item_ids.*' => ['bail', 'string', 'uuid', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        $invalid = __('app.work_record_item_list_invalid');

        return [
            'keep_item_ids.present' => $invalid,
            'keep_item_ids.array' => $invalid,
            'keep_item_ids.max' => $invalid,
            'add_item_ids.present' => $invalid,
            'add_item_ids.array' => $invalid,
            'add_item_ids.max' => $invalid,
        ];
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $validator instanceof ConcreteValidator) {
                return;
            }

            $data = (array) $validator->getData();
            $keep = $data['keep_item_ids'] ?? null;
            $add = $data['add_item_ids'] ?? null;

            if (! is_array($keep) || ! is_array($add) || $validator->errors()->hasAny(['keep_item_ids', 'add_item_ids'])) {
                return;
            }

            if (count($keep) + count($add) < 1) {
                $validator->errors()->add('keep_item_ids', __('app.work_record_items_required'));
            }
        });
    }
}
