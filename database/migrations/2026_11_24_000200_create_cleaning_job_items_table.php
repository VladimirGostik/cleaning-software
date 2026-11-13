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
        Schema::create('cleaning_job_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('cleaning_job_id')->constrained('cleaning_jobs')->restrictOnDelete();
            $table->foreignUuid('object_visit_item_id')->nullable()->constrained('object_visit_items')->nullOnDelete();
            $table->string('name', 255);
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            $table->index('cleaning_job_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX cleaning_job_items_job_item_unique ON cleaning_job_items '
            .'(cleaning_job_id, object_visit_item_id) WHERE object_visit_item_id IS NOT NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cleaning_job_items_job_item_unique');

        Schema::dropIfExists('cleaning_job_items');
    }
};
