<?php

declare(strict_types=1);

namespace App\Data\Tenants;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class IcoLookupData extends Data
{
    public function __construct(
        public string $name,
        public ?string $dic,
        public ?string $vat_number,
        public string $address_line,
        public string $city,
        public string $postal_code,
    ) {}
}
