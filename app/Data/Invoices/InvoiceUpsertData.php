<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Models\CleaningObject;
use Closure;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceUpsertData extends Data
{
    public function __construct(
        #[Nullable]
        public ?string $client_id,
        #[Nullable]
        public ?string $cleaning_object_id,
        #[Required]
        public InvoiceTypeEnum $type,
        #[Nullable]
        public ?InvoiceTemplateEnum $template,
        #[Required]
        public string $issue_date,
        #[Required]
        public string $delivery_date,
        #[Required]
        public string $due_date,
        #[Nullable]
        public ?string $period_from,
        #[Nullable]
        public ?string $period_to,
        #[Nullable]
        public ?string $customer_name,
        #[Nullable]
        public ?string $customer_ico,
        #[Nullable]
        public ?string $customer_dic,
        #[Nullable]
        public ?string $customer_vat_number,
        #[Nullable]
        public ?string $customer_street,
        #[Nullable]
        public ?string $customer_city,
        #[Nullable]
        public ?string $customer_postal_code,
        #[Nullable]
        public ?string $customer_country,
        #[Nullable]
        public ?string $customer_email,
        #[Nullable]
        public ?string $note,
        /** @var InvoiceItemData[] */
        #[Required, ArrayType, Min(1)]
        #[DataCollectionOf(InvoiceItemData::class)]
        public array $items,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return [
            'client_id' => [
                'nullable',
                'string',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'cleaning_object_id' => [
                'nullable',
                'string',
                Rule::exists('objects', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $clientId = request()->input('client_id');
                    if ($clientId === null) {
                        $fail('The cleaning object requires a client to be selected.');

                        return;
                    }

                    $exists = CleaningObject::withoutGlobalScopes()
                        ->where('id', $value)
                        ->where('client_id', $clientId)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $exists) {
                        $fail('The selected object does not belong to the selected client.');
                    }
                },
            ],
            'customer_name' => ['required_without:client_id', 'nullable', 'string', 'max:255'],
            'period_from' => ['required_if:type,monthly,special', 'nullable', 'date'],
            'period_to' => ['required_if:type,monthly,special', 'nullable', 'date', 'after_or_equal:period_from'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'delivery_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
        ];
    }
}
