<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();

            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('line_base', 12, 2)->default(0);
            $table->decimal('line_vat', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->smallInteger('position')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
