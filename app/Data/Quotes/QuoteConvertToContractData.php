<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use Illuminate\Validation\Rule;
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
        public readonly ?string $contract_template_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        /** @var string|null $tenantId */
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return [
            'contract_template_id' => [
                'nullable',
                'uuid',
                Rule::exists('contract_templates', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
