<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_breakdowns', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('cleaning_object_id')->constrained('objects')->restrictOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignUuid('source_quote_id')->nullable()->constrained('quotes')->nullOnDelete();

            $table->string('name');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('work_breakdowns', function (Blueprint $table): void {
            $table->index(['tenant_id', 'cleaning_object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_breakdowns');
    }
};
