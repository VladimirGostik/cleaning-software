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
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('cleaning_object_id')->nullable()->constrained('objects')->restrictOnDelete();
            $table->foreignUuid('recurring_invoice_id')->nullable()->constrained('recurring_invoices')->restrictOnDelete();

            $table->string('type');
            $table->string('status')->default('draft');
            $table->string('template')->default('classic');
            $table->string('number', 50)->nullable();
            $table->string('variable_symbol', 10)->nullable();

            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->date('issue_date');
            $table->date('delivery_date');
            $table->date('due_date');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->boolean('is_vat_payer')->default(false);
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('deposit', 12, 2)->default(0);
            $table->decimal('rounding_amount', 12, 2)->default(0);
            $table->json('vat_breakdown')->nullable();

            $table->string('constant_symbol', 10)->nullable();
            $table->string('specific_symbol', 10)->nullable();
            $table->string('payment_type')->default('transfer');
            $table->string('currency', 3)->default('EUR');
            $table->string('rounding_mode')->default('none');
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();

            $table->string('customer_name');
            $table->string('customer_representative')->nullable();
            $table->string('customer_ico')->nullable();
            $table->string('customer_dic')->nullable();
            $table->string('customer_vat_number')->nullable();
            $table->string('customer_street')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_postal_code', 16)->nullable();
            $table->string('customer_country', 2)->nullable();
            $table->string('customer_email')->nullable();

            $table->string('object_name')->nullable();
            $table->string('object_street')->nullable();
            $table->string('object_city')->nullable();
            $table->string('object_postal_code', 16)->nullable();

            $table->string('supplier_name');
            $table->string('supplier_ico')->nullable();
            $table->string('supplier_dic')->nullable();
            $table->string('supplier_vat_number')->nullable();
            $table->string('supplier_iban', 34)->nullable();
            $table->string('supplier_swift', 11)->nullable();
            $table->string('supplier_address_line')->nullable();
            $table->string('supplier_city')->nullable();
            $table->string('supplier_postal_code', 16)->nullable();
            $table->string('supplier_country', 2)->nullable();
            $table->string('supplier_contact_email')->nullable();
            $table->string('supplier_contact_phone', 30)->nullable();
            $table->string('supplier_registration_info')->nullable();

            $table->text('note')->nullable();

            // Reserved for spec IS EFA integration — no consumer in DTOs yet.
            $table->string('efa_status')->nullable();
            $table->string('efa_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'due_date']);
            $table->index(['tenant_id', 'issue_date']);
            $table->index(['tenant_id', 'client_id']);
            $table->index('recurring_invoice_id');
        });

        // Self-FK to the credit note's original invoice — added after table creation
        // (Postgres needs the primary key to exist before a self-referencing FK).
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignUuid('credited_invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->index('credited_invoice_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX invoices_tenant_number_unique ON invoices (tenant_id, number) WHERE deleted_at IS NULL AND number IS NOT NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
