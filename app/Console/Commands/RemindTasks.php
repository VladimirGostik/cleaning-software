<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Console\Command;

final class RemindTasks extends Command
{
    protected $signature = 'app:remind-tasks';

    protected $description = 'Send reminders for unfinished tasks whose remind_at has passed';

    public function handle(TaskService $tasks): int
    {
        $sent = 0;

        Task::unscopedForSystem()
            ->whereIn('status', TaskStatusEnum::activeValues())
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', now())
            ->whereNull('reminded_at')
            ->lazyById(200)
            ->each(function (Task $task) use ($tasks, &$sent): void {
                $tasks->sendReminder($task);
                $sent++;
            });

        $this->info("Task reminders sent: {$sent}.");

        return self::SUCCESS;
    }
}
