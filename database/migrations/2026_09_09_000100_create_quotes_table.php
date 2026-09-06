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
        Schema::create('quotes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('cleaning_object_id')->nullable()->constrained('objects')->restrictOnDelete();

            $table->string('status')->default('draft');
            $table->string('kind')->default('itemized');
            $table->string('number', 50)->nullable();
            $table->string('subject')->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_street')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_postal_code', 16)->nullable();

            $table->date('issue_date');
            $table->date('valid_until');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->boolean('is_vat_payer')->default(false);
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->string('currency', 3)->default('EUR');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('vat_breakdown')->nullable();

            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'kind']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'valid_until']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX quotes_tenant_number_unique ON quotes (tenant_id, number) WHERE number IS NOT NULL AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
