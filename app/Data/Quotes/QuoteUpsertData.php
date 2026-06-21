<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\CurrencyEnum;
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
final class QuoteUpsertData extends Data
{
    public function __construct(
        #[Required]
        public string $client_id,
        #[Nullable]
        public ?string $cleaning_object_id,
        #[Nullable]
        public ?string $subject,
        #[Required]
        public string $issue_date,
        #[Required]
        public string $valid_until,
        #[Nullable]
        public ?string $note,
        /** @var QuoteItemData[] */
        #[Required, ArrayType, Min(1)]
        #[DataCollectionOf(QuoteItemData::class)]
        public array $items,
        public CurrencyEnum $currency = CurrencyEnum::EUR,
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
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'items' => ['required', 'array', 'min:1'],
        ];
    }
}
