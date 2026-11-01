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
        Schema::create('task_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('task_id')->constrained('tasks')->restrictOnDelete();
            $table->foreignUuid('started_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->boolean('closed_task')->default(false);
            $table->jsonb('actions')->default('[]');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('tenant_id');
            $table->index('started_by_user_id');
        });

        DB::statement("CREATE UNIQUE INDEX task_runs_one_active_per_task_idx ON task_runs (task_id) WHERE status IN ('queued', 'running')");
    }

    public function down(): void
    {
        Schema::dropIfExists('task_runs');
    }
};
