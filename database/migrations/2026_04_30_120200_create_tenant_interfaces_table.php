<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_interfaces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->restrictOnDelete();

            $table->string('color', 20)->nullable();

            $table->string('invoice_template')->default('classic');
            $table->string('recurring_default_state')->default('draft');
            $table->string('default_constant_symbol', 10)->nullable();
            $table->string('default_payment_type')->default('transfer');
            $table->string('default_currency', 3)->default('EUR');
            $table->string('default_rounding_mode')->default('none');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_interfaces');
    }
};
