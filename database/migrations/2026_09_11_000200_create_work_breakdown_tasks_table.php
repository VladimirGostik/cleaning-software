<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_breakdown_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('work_breakdown_id')->constrained('work_breakdowns')->restrictOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('frequency', 32);
            $table->smallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['work_breakdown_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_breakdown_tasks');
    }
};
