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
final class ObjectUpdateData extends Data
{
    public function __construct(
        #[Required]
        public string $client_id,
        #[Required]
        public ObjectTypeEnum $type,
        #[Required, Max(255)]
        public string $name,
        #[Nullable, Max(255)]
        public ?string $street,
        #[Nullable, Max(255)]
        public ?string $city,
        #[Nullable, Max(16)]
        public ?string $postal_code,
        #[Required, Max(255)]
        public string $country,
        #[Nullable, Max(64)]
        public ?string $access_code,
        #[Nullable, Max(64)]
        public ?string $key_box_code,
        #[Nullable, Min(0)]
        public ?int $key_count,
        #[Nullable]
        public ?string $special_instructions,
        #[Nullable, Min(0)]
        public ?float $area_sqm,
        #[Nullable]
        public ?int $floor,
        public bool $is_active = true,
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
        ];
    }
}
