<?php

declare(strict_types=1);

namespace App\Data\Objects;

use App\Enums\ObjectTypeEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ObjectUpsertData extends Data
{
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
        public readonly bool $is_active = true,
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
        ];
    }
}
