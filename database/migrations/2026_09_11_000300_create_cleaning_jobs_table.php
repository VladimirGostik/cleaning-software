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
        Schema::create('cleaning_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('cleaning_object_id')->constrained('objects')->restrictOnDelete();
            $table->foreignUuid('assigned_membership_id')->nullable()->constrained('tenant_memberships')->nullOnDelete();
            $table->foreignUuid('work_breakdown_id')->nullable()->constrained('work_breakdowns')->nullOnDelete();
            $table->foreignUuid('work_breakdown_task_id')->nullable()->constrained('work_breakdown_tasks')->nullOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->string('type', 32);
            $table->string('status', 32)->default('unassigned');
            $table->date('scheduled_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('note')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'scheduled_date']);
            $table->index('cleaning_object_id');
            $table->index(['assigned_membership_id', 'scheduled_date']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX cleaning_jobs_recurrence_unique ON cleaning_jobs '
            .'(cleaning_object_id, work_breakdown_task_id, scheduled_date) '
            .'WHERE deleted_at IS NULL AND work_breakdown_task_id IS NOT NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cleaning_jobs_recurrence_unique');

        Schema::dropIfExists('cleaning_jobs');
    }
};
