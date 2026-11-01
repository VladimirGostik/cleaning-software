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
        Schema::create('task_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('task_id')->constrained('tasks')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('author_kind', 16);
            $table->foreignUuid('task_run_id')->nullable()->constrained('task_runs')->restrictOnDelete();
            $table->uuid('answers_comment_id')->nullable();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_id', 'created_at']);
            $table->index('tenant_id');
            $table->index('user_id');
        });

        Schema::table('task_comments', function (Blueprint $table): void {
            $table->foreign('answers_comment_id')->references('id')->on('task_comments')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE task_comments ADD CONSTRAINT task_comments_ai_has_run_check CHECK ((author_kind = 'ai') = (task_run_id IS NOT NULL))");
        DB::statement('CREATE UNIQUE INDEX task_comments_one_per_run_idx ON task_comments (task_run_id) WHERE task_run_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX task_comments_one_live_answer_idx ON task_comments (answers_comment_id) WHERE answers_comment_id IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};
