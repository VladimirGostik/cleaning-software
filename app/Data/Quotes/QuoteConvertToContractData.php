<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\DocumentTypeEnum;
use App\Rules\UsableDocumentTemplate;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class QuoteConvertToContractData extends Data
{
    public function __construct(
        #[Nullable, Uuid]
        public readonly ?string $document_template_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        /** @var string|null $tenantId */
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return [
            'document_template_id' => [
                'nullable',
                'uuid',
                new UsableDocumentTemplate(DocumentTypeEnum::ServiceAgreement),
            ],
        ];
    }
}
