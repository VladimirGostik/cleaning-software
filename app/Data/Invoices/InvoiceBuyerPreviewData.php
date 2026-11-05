<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Rules\ObjectBelongsToClient;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceBuyerPreviewData extends Data
{
    public function __construct(
        #[Required]
        public readonly string $client_id,
        #[Nullable]
        public readonly ?string $cleaning_object_id = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'client_id' => [
                'required',
                'uuid',
                Rule::exists('clients', 'id')->where('tenant_id', current_tenant_id())->whereNull('deleted_at'),
            ],
            'cleaning_object_id' => [
                'nullable',
                'uuid',
                Rule::exists('objects', 'id')->where('tenant_id', current_tenant_id())->whereNull('deleted_at'),
                new ObjectBelongsToClient,
            ],
        ];
    }
}
