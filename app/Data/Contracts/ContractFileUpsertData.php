<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractTermTypeEnum;
use App\Models\Contract;
use App\Rules\OwnedTemporaryMedia;
use App\Rules\TemporaryMediaConstraints;
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
final class ContractFileUpsertData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $title,
        #[Nullable, Max(50)]
        public readonly ?string $number,
        #[Required]
        public readonly ContractCategoryEnum $category,
        #[Required]
        public readonly ContractableTypeEnum $contractable_type,
        #[Required, Uuid]
        public readonly string $contractable_id,
        #[Required, Date]
        public readonly string $valid_from,
        #[Required]
        public readonly ContractTermTypeEnum $term_type,
        #[Nullable, Date]
        public readonly ?string $end_date = null,
        public readonly bool $is_signed = true,
        #[Nullable, Date]
        public readonly ?string $signed_on = null,
        #[Nullable, Max(5000)]
        public readonly ?string $notes = null,
        #[Nullable, Uuid]
        public readonly ?string $file_uuid = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $routeContract = request()->route('contract');

        return [
            'category' => ['required', Rule::enum(ContractCategoryEnum::class)->except([ContractCategoryEnum::Employment])],
            'contractable_type' => ['required', Rule::enum(ContractableTypeEnum::class)->except([ContractableTypeEnum::TenantMembership])],
            'end_date' => ['nullable', 'date', 'required_if:term_type,fixed', 'after_or_equal:valid_from'],
            'is_signed' => ['boolean'],
            'signed_on' => [
                'nullable',
                'date',
                'before_or_equal:today',
                Rule::prohibitedIf(static fn (): bool => in_array(request()->input('is_signed'), [false, 0, '0'], true)),
            ],
            'file_uuid' => request()->routeIs('token.contracts.store') ? ['prohibited'] : [
                $routeContract instanceof Contract ? 'nullable' : 'required',
                'uuid',
                new OwnedTemporaryMedia,
                new TemporaryMediaConstraints(
                    [],
                    config()->integer('documents.contract_files.max_size_kb'),
                    'app.contract_file_invalid_type',
                    'app.contract_file_too_large',
                    'documents.contract_files',
                ),
            ],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'end_date.required_if' => __('app.contract_end_date_required_for_fixed'),
            'signed_on.prohibited' => __('app.contract_signed_on_requires_signed'),
        ];
    }
}
