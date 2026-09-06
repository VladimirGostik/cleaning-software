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
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('contract_template_id')->nullable()->constrained('contract_templates')->nullOnDelete();
            $table->foreignUuid('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->uuidMorphs('contractable');

            $table->string('category', 32);
            $table->string('status', 32)->default('draft');
            $table->string('term_type', 32);
            $table->string('title', 255);
            $table->string('number', 50)->nullable();
            $table->text('body');

            $table->date('valid_from');
            $table->date('end_date')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category']);
            $table->index('quote_id');
            $table->index('contract_template_id');
        });

        DB::statement(
            "CREATE INDEX contracts_expiry_check ON contracts (end_date) WHERE status = 'active' AND term_type = 'fixed' AND deleted_at IS NULL",
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS contracts_expiry_check');

        Schema::dropIfExists('contracts');
    }
};
