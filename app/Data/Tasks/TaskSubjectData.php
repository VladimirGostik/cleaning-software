<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\ScheduledJob;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskSubjectData extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $url,
    ) {}

    /** Expects the `taskable` relation to be loaded. */
    public static function fromTask(Task $task): ?self
    {
        $subject = $task->taskable;

        if (! $subject instanceof Model || $task->taskable_type === null) {
            return null;
        }

        [$label, $route] = match (true) {
            $subject instanceof Invoice => [$subject->number ?? __('app.invoice_pdf_draft'), 'invoices.show'],
            $subject instanceof Quote => [$subject->number ?? __('app.quote_no_number'), 'quotes.show'],
            $subject instanceof Client => [$subject->name, 'clients.show'],
            $subject instanceof CleaningObject => [$subject->name, 'objects.show'],
            $subject instanceof ScheduledJob => [__('app.task_subject_job', ['date' => $subject->scheduled_date->toDateString()]), 'jobs.show'],
            $subject instanceof Contract => [$subject->title, 'contracts.show'],
            default => [$task->taskable_id ?? '', null],
        };

        return new self(
            type: array_search($task->taskable_type, Task::TASKABLE_TYPES, true) ?: $task->taskable_type,
            id: $task->taskable_id ?? '',
            label: $label,
            url: $route !== null ? route($route, $subject, false) : null,
        );
    }
}
