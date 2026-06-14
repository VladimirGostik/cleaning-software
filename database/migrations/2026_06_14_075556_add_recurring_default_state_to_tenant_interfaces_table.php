<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->string('recurring_default_state')->default('draft')->after('invoice_template');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->dropColumn('recurring_default_state');
        });
    }
};
