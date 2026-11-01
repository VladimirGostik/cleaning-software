<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Models\TenantMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeePersonalData extends Data
{
    public function __construct(
        public readonly ?string $birth_date,
        public readonly ?string $national_id_masked,
        public readonly ?string $street,
        public readonly ?string $city,
        public readonly ?string $postal_code,
        public readonly ?string $company_ico,
    ) {}

    public static function fromModel(TenantMembership $membership): self
    {
        $nationalId = $membership->national_id;
        $digits = $nationalId !== null ? preg_replace('/\D/', '', $nationalId) : null;

        return new self(
            birth_date: $membership->birth_date?->toDateString(),
            national_id_masked: $digits !== null && $digits !== '' ? '******/'.substr($digits, -4) : null,
            street: $membership->street,
            city: $membership->city,
            postal_code: $membership->postal_code,
            company_ico: $membership->company_ico,
        );
    }
}
