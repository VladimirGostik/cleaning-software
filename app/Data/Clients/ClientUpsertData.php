<?php

declare(strict_types=1);

namespace App\Data\Clients;

use App\Enums\ClientTypeEnum;
use App\Models\Client;
use Closure;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class ClientUpsertData extends Data
{
    public function __construct(
        #[Required]
        public readonly ClientTypeEnum $type,
        #[Required, Max(255)]
        public readonly string $name,
        #[Nullable, Max(32), RequiredIf('type', 'corporate')]
        public readonly ?string $ico,
        #[Nullable, Max(32)]
        public readonly ?string $dic,
        #[Nullable, Max(32)]
        public readonly ?string $vat_number,
        public readonly bool $is_vat_payer,
        #[Nullable, Max(255)]
        public readonly ?string $street,
        #[Nullable, Max(255)]
        public readonly ?string $city,
        #[Nullable, Max(16)]
        public readonly ?string $postal_code,
        #[Required, Max(255)]
        public readonly string $country,
        #[Nullable]
        public readonly ?string $note,
        /** @var DataCollection<int, ClientContactData> */
        #[DataCollectionOf(ClientContactData::class)]
        public readonly DataCollection $contacts,
    ) {}

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'ico' => __('app.client_ico'),
        ];
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $routeClient = request()->route('client');
        $clientId = $routeClient instanceof Client ? $routeClient->id : null;

        return [
            'ico' => [
                Rule::unique('clients', 'ico')
                    ->where('tenant_id', current_tenant_id())
                    ->whereNull('deleted_at')
                    ->ignore($clientId),
            ],
            'contacts' => [
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }

                    $primaryCount = collect($value)
                        ->filter(fn (mixed $contact): bool => is_array($contact) && (bool) ($contact['is_primary'] ?? false))
                        ->count();

                    if ($primaryCount > 1) {
                        $fail(__('app.client_contacts_multiple_primary'));
                    }
                },
            ],
        ];
    }
}
