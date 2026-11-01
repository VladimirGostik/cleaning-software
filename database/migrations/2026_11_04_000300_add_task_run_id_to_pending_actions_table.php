<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_actions', function (Blueprint $table): void {
            $table->foreignUuid('task_run_id')->nullable()->after('user_id')->constrained('task_runs')->restrictOnDelete();
            $table->index('task_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('pending_actions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('task_run_id');
        });
    }
};
