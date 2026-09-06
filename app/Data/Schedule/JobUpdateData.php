<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Enums\JobTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** No `assigned_membership_id` — re-assignment goes only through `JobAssignData` (D10). */
#[TypeScript]
#[MergeValidationRules]
final class JobUpdateData extends Data
{
    public function __construct(
        #[Required, Uuid]
        public readonly string $cleaning_object_id,
        #[Required]
        public readonly JobTypeEnum $type,
        #[Required, Date]
        public readonly string $scheduled_date,
        #[Nullable]
        public readonly ?string $start_time = null,
        #[Nullable]
        public readonly ?string $end_time = null,
        #[Nullable, Max(2000)]
        public readonly ?string $note = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        /** @var string|null $tenantId */
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return [
            'cleaning_object_id' => [
                Rule::exists('objects', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'end_time.after' => __('app.job_end_after_start'),
        ];
    }
}
