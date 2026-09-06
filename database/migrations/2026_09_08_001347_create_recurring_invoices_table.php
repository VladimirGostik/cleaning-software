<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->foreignUuid('cleaning_object_id')->nullable()->constrained('objects')->restrictOnDelete();

            $table->string('name');
            $table->string('type')->default('monthly');
            $table->string('template')->nullable();
            $table->string('frequency');
            $table->unsignedTinyInteger('day_of_month');
            $table->string('status')->default('active');
            $table->boolean('auto_issue')->default(false);

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedInteger('occurrences_limit')->nullable();
            $table->unsignedInteger('occurrences_generated')->default(0);
            $table->date('next_run_at')->nullable();
            $table->timestamp('last_generated_at')->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_representative')->nullable();
            $table->string('customer_ico')->nullable();
            $table->string('customer_dic')->nullable();
            $table->string('customer_vat_number')->nullable();
            $table->string('customer_street')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_postal_code', 16)->nullable();
            $table->string('customer_country', 2)->nullable();
            $table->string('customer_email')->nullable();

            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedSmallInteger('due_days')->default(14);
            $table->decimal('deposit', 12, 2)->default(0);
            $table->text('note')->nullable();

            $table->string('constant_symbol', 10)->nullable();
            $table->string('payment_type')->default('transfer');
            $table->string('currency', 3)->default('EUR');
            $table->string('rounding_mode')->default('none');
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'next_run_at']);
            $table->index(['tenant_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices');
    }
};
