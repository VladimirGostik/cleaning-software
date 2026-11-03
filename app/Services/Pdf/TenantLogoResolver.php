<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Tenant;

final readonly class TenantLogoResolver
{
    public function __construct(private MediaDataUri $encoder) {}

    public function forTenant(Tenant $tenant): ?string
    {
        return $this->encoder->encode($tenant->logoMedia(), $tenant->id, 'quote.logo');
    }
}
