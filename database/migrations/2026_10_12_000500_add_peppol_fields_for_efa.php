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
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('peppol_participant_id', 64)->nullable();
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->string('peppol_participant_id', 64)->nullable();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('customer_peppol_participant_id', 64)->nullable();
            $table->string('supplier_peppol_participant_id', 64)->nullable();
            $table->timestamp('efa_submitted_at')->nullable();
            $table->text('efa_last_error')->nullable();
        });

        DB::table('invoices')->whereNull('efa_status')->update(['efa_status' => 'none']);

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('efa_status')->default('none')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('efa_status')->nullable()->default(null)->change();
            $table->dropColumn(['customer_peppol_participant_id', 'supplier_peppol_participant_id', 'efa_submitted_at', 'efa_last_error']);
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('peppol_participant_id');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('peppol_participant_id');
        });
    }
};
