<?php

declare(strict_types=1);

namespace App\Data\DocumentTemplates;

use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class DocumentTemplateUpsertData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        #[Required]
        public readonly DocumentTypeEnum $type,
        #[Required, Max(200000)]
        public readonly string $body,
        public readonly bool $show_header,
        public readonly bool $show_footer,
        public readonly bool $is_active,
        #[Nullable]
        public readonly ?EmploymentContractTypeEnum $employment_type = null,
    ) {}

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'name' => __('validation.attributes.title'),
        ];
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DocumentTypeEnum::class)->only(DocumentTypeEnum::templatable())],
            'employment_type' => ['nullable', 'prohibited_unless:type,'.DocumentTypeEnum::Employment->value],
        ];
    }
}
