<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaning_jobs', function (Blueprint $table): void {
            $table->uuid('client_uuid')->nullable();
        });

        DB::statement(
            'CREATE UNIQUE INDEX cleaning_jobs_client_uuid_unique ON cleaning_jobs '
            .'(tenant_id, client_uuid) WHERE client_uuid IS NOT NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cleaning_jobs_client_uuid_unique');

        Schema::table('cleaning_jobs', function (Blueprint $table): void {
            $table->dropColumn('client_uuid');
        });
    }
};
