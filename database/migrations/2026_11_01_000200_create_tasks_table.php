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
        Schema::create('tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('assigned_membership_id')->nullable()->constrained('tenant_memberships')->restrictOnDelete();

            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 16)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('remind_at')->nullable();
            $table->timestampTz('reminded_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('source', 16)->default('manual');
            $table->string('taskable_type')->nullable();
            $table->uuid('taskable_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'due_at']);
            $table->index(['tenant_id', 'assigned_membership_id']);
            $table->index(['taskable_type', 'taskable_id']);
        });

        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_global_not_assigned_check CHECK (tenant_id IS NOT NULL OR assigned_membership_id IS NULL)');
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_taskable_pair_check CHECK ((taskable_type IS NULL) = (taskable_id IS NULL))');
        DB::statement('CREATE INDEX tasks_global_creator_status_idx ON tasks (created_by_user_id, status) WHERE tenant_id IS NULL');
        DB::statement("CREATE INDEX tasks_pending_reminder_idx ON tasks (remind_at) WHERE reminded_at IS NULL AND status = 'open'");
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
