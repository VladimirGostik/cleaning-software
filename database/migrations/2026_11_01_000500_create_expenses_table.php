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
        Schema::create('expenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('created_by_user_id')->constrained('users')->restrictOnDelete();

            $table->string('status', 16)->default('draft');
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('vat_amount', 12, 2)->nullable();
            $table->string('supplier_name', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('category', 32)->nullable();
            $table->string('source', 16)->default('manual');
            $table->string('bank_reference', 100)->nullable();
            $table->char('external_hash', 64)->nullable();
            $table->jsonb('ai_proposal')->nullable();
            $table->timestampTz('confirmed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'date']);
            $table->index(['tenant_id', 'category']);
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_amount_positive_check CHECK (amount > 0)');
        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_vat_not_negative_check CHECK (vat_amount IS NULL OR vat_amount >= 0)');
        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expenses_confirmed_has_category_check CHECK (status <> 'confirmed' OR category IS NOT NULL)");
        DB::statement('CREATE UNIQUE INDEX expenses_tenant_external_hash_unique ON expenses (tenant_id, external_hash) WHERE external_hash IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
