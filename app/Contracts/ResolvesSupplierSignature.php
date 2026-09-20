<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Invoice;
use App\Models\Tenant;

interface ResolvesSupplierSignature
{
    /** Snapshot FK on `$invoice` when set (frozen at issue), else the tenant's current signature. */
    public function forInvoice(Invoice $invoice): ?string;

    /** Settings-page live preview — always the tenant's current signature. */
    public function forTenant(Tenant $tenant): ?string;
}
