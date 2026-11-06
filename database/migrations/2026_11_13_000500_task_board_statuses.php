<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::update("UPDATE tasks SET status = 'done', completed_at = COALESCE(completed_at, updated_at, now()) WHERE status = 'cancelled'");

        DB::statement('DROP INDEX IF EXISTS tasks_pending_reminder_idx');
        DB::statement("CREATE INDEX tasks_pending_reminder_idx ON tasks (remind_at) WHERE reminded_at IS NULL AND status IN ('open','in_progress','waiting','nice_to_have')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tasks_pending_reminder_idx');
        DB::statement("CREATE INDEX tasks_pending_reminder_idx ON tasks (remind_at) WHERE reminded_at IS NULL AND status = 'open'");
    }
};
