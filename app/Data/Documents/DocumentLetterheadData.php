<?php

declare(strict_types=1);

namespace App\Data\Documents;

use App\Models\Tenant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DocumentLetterheadData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $address,
        public readonly ?string $ico,
    ) {}

    public static function fromTenant(Tenant $tenant): self
    {
        $address = trim(implode(', ', array_filter([
            $tenant->address_line,
            trim(implode(' ', array_filter([$tenant->postal_code, $tenant->city]))),
        ])));

        return new self(
            name: $tenant->name,
            address: $address !== '' ? $address : null,
            ico: $tenant->ico,
        );
    }
}
