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
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedInteger('legacy_id')->nullable()->after('id');
        });

        DB::statement('CREATE UNIQUE INDEX invoices_legacy_id_unique ON invoices (legacy_id) WHERE legacy_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS invoices_legacy_id_unique');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('legacy_id');
        });
    }
};
