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
            // Self-FK added separately after table creation (PostgreSQL requires PK to exist first)
            $table->uuid('credited_invoice_id')->nullable();

            $table->string('type');
            $table->string('status')->default('draft');
            $table->string('template')->default('classic');

            $table->string('number')->nullable();
            $table->string('variable_symbol')->nullable();

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
            $table->json('vat_breakdown')->nullable();

            // Customer snapshot
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

            // Object snapshot
            $table->string('object_name')->nullable();
            $table->string('object_street')->nullable();
            $table->string('object_city')->nullable();
            $table->string('object_postal_code', 16)->nullable();

            // Supplier snapshot
            $table->string('supplier_name');
            $table->string('supplier_ico')->nullable();
            $table->string('supplier_dic')->nullable();
            $table->string('supplier_vat_number')->nullable();
            $table->string('supplier_iban')->nullable();
            $table->string('supplier_address_line')->nullable();
            $table->string('supplier_city')->nullable();
            $table->string('supplier_postal_code', 16)->nullable();
            $table->string('supplier_country', 2)->nullable();
            $table->string('supplier_contact_email')->nullable();
            $table->string('supplier_contact_phone')->nullable();
            $table->string('supplier_registration_info')->nullable();

            $table->text('note')->nullable();

            // D10 — IS EFA reservation (NOT in DTOs)
            $table->string('efa_status')->nullable();
            $table->string('efa_id')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Self-FK must be added after table+PK exist (PostgreSQL restriction)
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreign('credited_invoice_id')
                ->references('id')
                ->on('invoices')
                ->restrictOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'due_date']);
        });

        // Partial unique index on (tenant_id, number) — mirrors tenant_invitations pattern
        DB::statement(
            'CREATE UNIQUE INDEX invoices_tenant_number_unique ON invoices (tenant_id, number) WHERE number IS NOT NULL AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
