<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Models\TenantMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeePersonalFormData extends Data
{
    public function __construct(
        public readonly ?string $birth_date,
        public readonly ?string $national_id,
        public readonly ?string $street,
        public readonly ?string $city,
        public readonly ?string $postal_code,
    ) {}

    public static function fromModel(TenantMembership $membership): self
    {
        return new self(
            birth_date: $membership->birth_date?->toDateString(),
            national_id: $membership->national_id,
            street: $membership->street,
            city: $membership->city,
            postal_code: $membership->postal_code,
        );
    }

    public static function blank(): self
    {
        return new self(null, null, null, null, null);
    }
}
