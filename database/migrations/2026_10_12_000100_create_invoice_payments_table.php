<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();

            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('payment_type');
            $table->string('source')->default('manual');
            $table->string('external_reference', 128)->nullable();
            $table->text('note')->nullable();
            $table->foreignUuid('recorded_by_user_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
