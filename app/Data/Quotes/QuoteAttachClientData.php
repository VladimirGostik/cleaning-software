<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteAttachClientData extends Data
{
    public function __construct(
        public string $client_id,
        #[Nullable]
        public ?string $cleaning_object_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return [
            'client_id' => [
                'required',
                'string',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'cleaning_object_id' => [
                'nullable',
                'string',
                Rule::exists('objects', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
                QuoteUpsertData::objectBelongsToClientRule(),
            ],
        ];
    }
}
