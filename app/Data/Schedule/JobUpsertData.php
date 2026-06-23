<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Enums\JobTypeEnum;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobUpsertData extends Data
{
    public function __construct(
        #[Required]
        public string $cleaning_object_id,
        #[Required]
        public JobTypeEnum $type,
        #[Required]
        public string $scheduled_date,
        public ?string $start_time = null,
        public ?string $end_time = null,
        public ?string $assigned_membership_id = null,
        public ?string $contract_id = null,
        public ?string $note = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $tenantId = App::make('current_tenant_id');

        return [
            'cleaning_object_id' => [
                'required',
                'uuid',
                Rule::exists('objects', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'scheduled_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'assigned_membership_id' => [
                'nullable',
                'uuid',
                Rule::exists('tenant_memberships', 'id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],
            'contract_id' => [
                'nullable',
                'uuid',
                Rule::exists('contracts', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
        ];
    }
}
