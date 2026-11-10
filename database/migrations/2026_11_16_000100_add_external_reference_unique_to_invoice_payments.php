<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX invoice_payments_tenant_external_reference_unique ON invoice_payments (tenant_id, external_reference) WHERE external_reference IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS invoice_payments_tenant_external_reference_unique');
    }
};
