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
        Schema::create('contracts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('contract_template_id')->nullable()->constrained('contract_templates')->nullOnDelete();
            $table->string('contractable_type', 128);
            $table->uuid('contractable_id');
            $table->string('category', 64);
            $table->string('status', 32)->default('draft');
            $table->string('term_type', 32);
            $table->string('title', 255);
            $table->string('reference_number', 128)->nullable();
            $table->longText('body');
            $table->date('valid_from');
            $table->date('end_date')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('contract_template_id');
            $table->index(['contractable_type', 'contractable_id']);
        });

        DB::statement(
            'CREATE INDEX contracts_tenant_status_active ON contracts(tenant_id, status) WHERE deleted_at IS NULL',
        );
        DB::statement(
            "CREATE INDEX contracts_expiry_check ON contracts(tenant_id, end_date) WHERE status = 'active' AND term_type = 'fixed' AND deleted_at IS NULL",
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS contracts_tenant_status_active');
        DB::statement('DROP INDEX IF EXISTS contracts_expiry_check');
        Schema::dropIfExists('contracts');
    }
};
