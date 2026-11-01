<?php

declare(strict_types=1);

namespace App\Data\Objects;

use App\Enums\ObjectTypeEnum;
use App\Rules\AtMostOnePrimaryContact;
use App\Rules\PeppolParticipantId;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ObjectUpsertData extends Data
{
    public const array BILLING_FIELDS = [
        'billing_name', 'billing_ico', 'billing_dic', 'billing_vat_number',
        'billing_peppol_participant_id', 'billing_street', 'billing_city', 'billing_postal_code',
    ];

    public function __construct(
        #[Required]
        public readonly string $client_id,
        #[Required]
        public readonly ObjectTypeEnum $type,
        #[Required, Max(255)]
        public readonly string $name,
        #[Nullable, Max(255)]
        public readonly ?string $street,
        #[Nullable, Max(255)]
        public readonly ?string $city,
        #[Nullable, Max(16)]
        public readonly ?string $postal_code,
        #[Required, Max(255)]
        public readonly string $country,
        #[Nullable, Max(64)]
        public readonly ?string $access_code,
        #[Nullable, Max(64)]
        public readonly ?string $key_box_code,
        #[Nullable, Min(0)]
        public readonly ?int $key_count,
        #[Nullable]
        public readonly ?string $special_instructions,
        #[Nullable, Min(0)]
        public readonly ?float $area_sqm,
        #[Nullable]
        public readonly ?int $floor,
        public readonly bool $is_active,
        /** @var DataCollection<int, ObjectContactData> */
        #[DataCollectionOf(ObjectContactData::class)]
        public readonly DataCollection $contacts,
        #[Nullable, Max(255)]
        public readonly ?string $billing_name = null,
        #[Nullable, Max(32)]
        public readonly ?string $billing_ico = null,
        #[Nullable, Max(32)]
        public readonly ?string $billing_dic = null,
        #[Nullable, Max(32)]
        public readonly ?string $billing_vat_number = null,
        #[Nullable, Max(64)]
        public readonly ?string $billing_peppol_participant_id = null,
        #[Nullable, Max(255)]
        public readonly ?string $billing_street = null,
        #[Nullable, Max(255)]
        public readonly ?string $billing_city = null,
        #[Nullable, Max(16)]
        public readonly ?string $billing_postal_code = null,
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
            'contacts' => [new AtMostOnePrimaryContact('app.object_contacts_multiple_primary')],
            'billing_name' => ['required_with:'.implode(',', array_slice(self::BILLING_FIELDS, 1))],
            'billing_peppol_participant_id' => [new PeppolParticipantId],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'billing_name.required_with' => __('app.object_billing_name_required'),
        ];
    }
}
