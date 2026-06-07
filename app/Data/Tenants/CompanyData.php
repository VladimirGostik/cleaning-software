<?php

declare(strict_types=1);

namespace App\Data\Tenants;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CompanyData extends Data
{
    public function __construct(
        #[Required]
        public string $name,
        #[Required]
        public string $ico,
        public ?string $dic = null,
        public ?string $vat_number = null,
        #[Required]
        public bool $is_vat_payer = false,
        #[Required]
        public string $address_line = '',
        #[Required]
        public string $city = '',
        #[Required]
        public string $postal_code = '',
        public string $country = 'SK',
    ) {}
}
