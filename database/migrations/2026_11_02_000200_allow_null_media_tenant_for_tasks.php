<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE media ALTER COLUMN tenant_id DROP NOT NULL');
        DB::statement("ALTER TABLE media ADD CONSTRAINT media_tenant_required_unless_task CHECK (tenant_id IS NOT NULL OR model_type = 'App\\Models\\Task')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE media DROP CONSTRAINT media_tenant_required_unless_task');
        DB::statement('ALTER TABLE media ALTER COLUMN tenant_id SET NOT NULL');
    }
};
