<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Expense;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\Guard\PromptGuard;
use App\Services\ExpenseService;
use App\Services\TaskAttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class AssistantAttachmentService
{
    public function __construct(
        private ToolContext $context,
        private ExpenseService $expenses,
        private TaskAttachmentService $tasks,
    ) {}

    /** @param  list<UploadedFile>  $files */
    public function attach(AssistantTurn $turn, array $files, User $user): ?string
    {
        if ($files === []) {
            return null;
        }

        $records = $turn->createdRecords();

        if ($records === []) {
            return __('app.assistant_attachments_read_only');
        }

        $saved = [];
        $unsaved = [];
        $undocumented = [];
        $expenseGroups = [];

        foreach ($records as $record) {
            $numbers = $record['numbers'] === [] && count($files) === 1 ? [1] : $record['numbers'];

            if ($numbers === []) {
                $unsaved[] = $this->label($record);

                continue;
            }

            if ($record['kind'] === 'expense') {
                $expenseGroups[$record['company_id'].'|'.$numbers[0]][] = $record + ['number' => $numbers[0]];

                continue;
            }

            $this->attachToTask($record, $numbers, $files, $user, $saved, $unsaved);
        }

        foreach ($expenseGroups as $group) {
            $this->attachExpenseGroup($group, $files, $user, $saved, $unsaved, $undocumented);
        }

        return $this->note($saved, $unsaved, $undocumented);
    }

    /**
     * @param  array{kind: string, id: string, company_id: string, label: string, numbers: list<int>}  $record
     * @param  list<int>  $numbers
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $saved
     * @param  list<string>  $unsaved
     */
    private function attachToTask(array $record, array $numbers, array $files, User $user, array &$saved, array &$unsaved): void
    {
        $label = $this->label($record);

        try {
            $outcome = $this->context->run($user, $record['company_id'], function () use ($record, $numbers, $files, $user): array {
                $task = Task::query()->findOrFail($record['id']);
                Gate::forUser($user)->authorize('update', $task);

                $ok = [];
                $failed = [];

                foreach ($numbers as $number) {
                    try {
                        $this->tasks->add($task, $files[$number - 1], $user);
                        $ok[] = $number;
                    } catch (Throwable $exception) {
                        Log::warning('assistant.attachment_failed', ['exception' => $exception::class]);
                        $failed[] = $number;
                    }
                }

                return ['ok' => $ok, 'failed' => $failed];
            });
        } catch (Throwable $exception) {
            Log::warning('assistant.attachment_failed', ['exception' => $exception::class]);
            $unsaved[] = $label;

            return;
        }

        if ($outcome['failed'] === []) {
            $saved[] = $label;

            return;
        }

        if ($outcome['ok'] === []) {
            $unsaved[] = $label;

            return;
        }

        foreach ($outcome['ok'] as $number) {
            $saved[] = $label.' ('.$this->fileName($files[$number - 1]).')';
        }

        foreach ($outcome['failed'] as $number) {
            $unsaved[] = $label.' ('.$this->fileName($files[$number - 1]).')';
        }
    }

    /**
     * @param  list<array{kind: string, id: string, company_id: string, label: string, numbers: list<int>, number: int}>  $group
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $saved
     * @param  list<string>  $unsaved
     * @param  list<string>  $undocumented
     */
    private function attachExpenseGroup(array $group, array $files, User $user, array &$saved, array &$unsaved, array &$undocumented): void
    {
        $labels = array_map(fn (array $record): string => $this->label($record), $group);

        try {
            $file = $files[$group[0]['number'] - 1];

            $outcome = $this->context->run($user, $group[0]['company_id'], function () use ($group, $user, $file): array {
                $found = Expense::query()->whereIn('id', array_column($group, 'id'))->get()->keyBy('id');
                $attachable = [];
                $skipped = [];

                foreach ($group as $index => $record) {
                    $expense = $found->get($record['id']);

                    if ($expense === null || Gate::forUser($user)->denies('update', $expense)) {
                        continue;
                    }

                    if ($expense->is_undocumented) {
                        $skipped[] = $index;

                        continue;
                    }

                    $attachable[$index] = $expense;
                }

                if ($attachable !== []) {
                    $this->expenses->attachReceiptToMany(array_values($attachable), $file);
                }

                return ['ok' => array_keys($attachable), 'skipped' => $skipped];
            });
        } catch (Throwable $exception) {
            Log::warning('assistant.attachment_failed', ['exception' => $exception::class]);
            array_push($unsaved, ...$labels);

            return;
        }

        foreach ($labels as $index => $label) {
            if (in_array($index, $outcome['ok'], true)) {
                $saved[] = $label;
            } elseif (in_array($index, $outcome['skipped'], true)) {
                $undocumented[] = $label;
            } else {
                $unsaved[] = $label;
            }
        }
    }

    private function fileName(UploadedFile $file): string
    {
        return mb_substr(PromptGuard::stripInvisible($file->getClientOriginalName()), 0, 60);
    }

    /** @param  array{kind: string, label: string}  $record */
    private function label(array $record): string
    {
        $noun = __('app.assistant_noun_'.$record['kind']);

        return $record['label'] === '' ? $noun : $noun.' "'.$record['label'].'"';
    }

    /**
     * @param  list<string>  $saved
     * @param  list<string>  $unsaved
     * @param  list<string>  $undocumented
     */
    private function note(array $saved, array $unsaved, array $undocumented): string
    {
        $parts = [];

        if ($saved !== []) {
            $parts[] = __('app.assistant_attachment_saved', ['records' => implode(', ', $saved)]);
        }

        if ($unsaved !== []) {
            $parts[] = __('app.assistant_attachment_not_saved', ['records' => implode(', ', $unsaved)]);
        }

        if ($undocumented !== []) {
            $parts[] = __('app.assistant_attachment_undocumented', ['records' => implode(', ', $undocumented)]);
        }

        return implode(' ', $parts);
    }
}
