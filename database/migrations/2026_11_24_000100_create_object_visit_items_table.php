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
        Schema::create('object_visit_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('cleaning_object_id')->constrained('objects')->restrictOnDelete();
            $table->string('name', 255);
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cleaning_object_id', 'position']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX object_visit_items_name_unique ON object_visit_items '
            .'(cleaning_object_id, name) WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS object_visit_items_name_unique');

        Schema::dropIfExists('object_visit_items');
    }
};
