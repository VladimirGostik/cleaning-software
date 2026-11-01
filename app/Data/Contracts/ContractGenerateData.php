<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractTermTypeEnum;
use App\Rules\UsableDocumentTemplate;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class ContractGenerateData extends Data
{
    public function __construct(
        #[Required]
        public readonly ContractCategoryEnum $category,
        #[Required]
        public readonly ContractTermTypeEnum $term_type,
        #[Required]
        public readonly ContractableTypeEnum $contractable_type,
        #[Required, Uuid]
        public readonly string $contractable_id,
        #[Required, Date]
        public readonly string $valid_from,
        #[Nullable, Uuid]
        public readonly ?string $document_template_id = null,
        #[Nullable, Max(200000)]
        public readonly ?string $body = null,
        #[Nullable, Max(255)]
        public readonly ?string $title = null,
        #[Nullable, Max(50)]
        public readonly ?string $number = null,
        #[Nullable, Date]
        public readonly ?string $end_date = null,
        public readonly ?EmploymentContractUpsertData $employment = null,
        #[Nullable, Uuid]
        public readonly ?string $quote_id = null,
        public readonly bool $show_header = true,
        public readonly bool $show_footer = true,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        /** @var string|null $tenantId */
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return [
            'contractable_id' => [
                'required',
                'uuid',
                function (string $attribute, mixed $value, Closure $fail) use ($tenantId): void {
                    $rawType = request()->input('contractable_type');
                    $type = is_string($rawType) ? ContractableTypeEnum::tryFrom($rawType) : null;

                    if ($type === null) {
                        return;
                    }

                    $exists = DB::table($type->table())
                        ->where('id', $value)
                        ->where('tenant_id', $tenantId)
                        ->when($type !== ContractableTypeEnum::TenantMembership, fn ($q) => $q->whereNull('deleted_at'))
                        ->exists();

                    if (! $exists) {
                        $fail(__('app.contract_invalid_contractable'));
                    }
                },
            ],
            'document_template_id' => [
                'nullable',
                'uuid',
                new UsableDocumentTemplate,
            ],
            'quote_id' => [
                'nullable',
                'uuid',
                Rule::exists('quotes', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'end_date' => ['nullable', 'date', 'required_if:term_type,fixed', 'after_or_equal:valid_from'],
            'employment' => ['nullable', 'array', 'required_if:category,employment'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'end_date.required_if' => __('app.contract_end_date_required_for_fixed'),
            'employment.required_if' => __('app.contract_employment_required'),
        ];
    }
}
