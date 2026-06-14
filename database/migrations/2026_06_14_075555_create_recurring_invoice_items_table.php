<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoice_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('recurring_invoice_id')->constrained('recurring_invoices')->cascadeOnDelete();

            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->smallInteger('position')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_items');
    }
};
