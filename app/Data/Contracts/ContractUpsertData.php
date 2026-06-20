<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Enums\ContractCategoryEnum;
use App\Enums\ContractTermTypeEnum;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractUpsertData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $title,
        #[Nullable, Max(128)]
        public ?string $reference_number,
        #[Required]
        public ContractCategoryEnum $category,
        #[Required]
        public ContractTermTypeEnum $term_type,
        #[Required]
        public string $contractable_type,
        #[Required]
        public string $contractable_id,
        #[Nullable]
        public ?string $contract_template_id,
        #[Required]
        public string $body,
        #[Required]
        public string $valid_from,
        #[Nullable]
        public ?string $end_date = null,
        #[Nullable]
        public ?string $notes = null,
        #[Nullable]
        public ?EmploymentContractUpsertData $employment = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return [
            'contractable_type' => ['required', Rule::in(['cleaning_object', 'tenant_membership'])],
            'contractable_id' => [
                'required',
                'uuid',
                function (string $attr, mixed $value, Closure $fail) use ($tenantId): void {
                    $type = request()->input('contractable_type');
                    $table = match ($type) {
                        'cleaning_object' => 'objects',
                        'tenant_membership' => 'tenant_memberships',
                        default => null,
                    };

                    if ($table === null) {
                        return;
                    }

                    $exists = DB::table($table)
                        ->where('id', $value)
                        ->where('tenant_id', $tenantId)
                        ->when($table === 'objects', fn ($q) => $q->whereNull('deleted_at'))
                        ->exists();

                    if (! $exists) {
                        $fail(__('app.contracts.invalid_contractable'));
                    }
                },
            ],
            'contract_template_id' => [
                'nullable',
                'uuid',
                Rule::exists('contract_templates', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
