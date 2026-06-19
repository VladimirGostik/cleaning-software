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
            $table->string('default_constant_symbol', 10)->nullable()->after('recurring_default_state');
            $table->string('default_payment_type')->default('transfer')->after('default_constant_symbol');
            $table->string('default_currency', 3)->default('EUR')->after('default_payment_type');
            $table->string('default_rounding_mode')->default('none')->after('default_currency');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_interfaces', function (Blueprint $table): void {
            $table->dropColumn([
                'default_constant_symbol',
                'default_payment_type',
                'default_currency',
                'default_rounding_mode',
            ]);
        });
    }
};
