<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('template');
        });

        Schema::table('recurring_invoices', function (Blueprint $table): void {
            $table->dropColumn('template');
        });

        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->dropColumn('invoice_template');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('template')->default('classic');
        });

        Schema::table('recurring_invoices', function (Blueprint $table): void {
            $table->string('template')->nullable();
        });

        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->string('invoice_template')->default('classic');
        });
    }
};
