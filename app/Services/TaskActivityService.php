<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tasks\TaskActivityChangeData;
use App\Data\Tasks\TaskActivityEntryData;
use App\Data\Tasks\TaskActivityFeedData;
use App\Enums\TaskActivityFieldEnum;
use App\Enums\TaskActivityKindEnum;
use App\Models\Activity;
use App\Models\Task;
use App\Models\TenantMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class TaskActivityService
{
    private const array EVENTS = ['updated', 'attachment_added', 'attachment_deleted'];

    /** The viewer passed task visibility upstream; the feed shows only this task's rows. */
    public function for(Task $task, User $viewer): TaskActivityFeedData
    {
        $configured = config('tasks.activity.limit');
        $limit = max(1, is_int($configured) ? $configured : 100);

        $rows = Activity::query()
            ->where('subject_type', $task->getMorphClass())
            ->where('subject_id', $task->id)
            ->whereIn('event', self::EVENTS)
            ->with('causer')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasOlder = $rows->count() > $limit;
        $rows = $rows->take($limit)->reverse()->values();

        $names = $this->assigneeNames($rows);
        $entries = [];

        if (! $hasOlder) {
            $task->loadMissing('creator');
            $entries[] = new TaskActivityEntryData('created', TaskActivityKindEnum::Created, $task->creator?->name, $task->created_at->toIso8601String(), [], null);
        }

        foreach ($this->groups($rows) as $group) {
            $entry = $this->entry($group, $names);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return new TaskActivityFeedData($entries, $hasOlder);
    }

    /**
     * @param  Collection<int, Activity>  $rows
     * @return list<list<Activity>>
     */
    private function groups(Collection $rows): array
    {
        $minutes = config('tasks.activity.coalesce_minutes');
        $window = (is_int($minutes) ? $minutes : 10) * 60;
        $groups = [];
        $current = [];

        foreach ($rows as $row) {
            $previous = $current[count($current) - 1] ?? null;
            $joins = $previous !== null
                && $row->event === 'updated'
                && $previous->event === 'updated'
                && $row->causer_id === $previous->causer_id
                && ($row->created_at?->getTimestamp() ?? 0) - ($previous->created_at?->getTimestamp() ?? 0) <= $window;

            if (! $joins && $current !== []) {
                $groups[] = $current;
                $current = [];
            }

            $current[] = $row;
        }

        return $current === [] ? $groups : [...$groups, $current];
    }

    /**
     * @param  list<Activity>  $group
     * @param  Collection<string, string>  $names
     */
    private function entry(array $group, Collection $names): ?TaskActivityEntryData
    {
        $last = $group[count($group) - 1];
        $actor = $last->causer instanceof User ? $last->causer->name : null;
        $kind = TaskActivityKindEnum::from((string) $last->event);

        if ($kind !== TaskActivityKindEnum::Updated) {
            $name = $last->propertiesArray()['name'] ?? null;

            return new TaskActivityEntryData((string) $last->id, $kind, $actor, (string) $last->created_at?->toIso8601String(), [], is_string($name) ? $name : null);
        }

        $changes = $this->changes($group, $names);

        return $changes === [] ? null : new TaskActivityEntryData((string) $last->id, $kind, $actor, (string) $last->created_at?->toIso8601String(), $changes, null);
    }

    /**
     * @param  list<Activity>  $group
     * @param  Collection<string, string>  $names
     * @return list<TaskActivityChangeData>
     */
    private function changes(array $group, Collection $names): array
    {
        $changes = [];

        foreach (TaskActivityFieldEnum::cases() as $field) {
            $touched = false;
            $from = null;
            $to = null;

            foreach ($group as $row) {
                $new = $this->snapshot($row, 'attributes', $field);
                $old = $this->snapshot($row, 'old', $field);

                if ($new === null && $old === null) {
                    continue;
                }

                if (! $touched) {
                    $from = $old;
                    $touched = true;
                }

                $to = $new;
            }

            if ($touched && $this->flatten($from) !== $this->flatten($to)) {
                $changes[] = $field->showsValues()
                    ? new TaskActivityChangeData($field, $this->display($field, $from, $names), $this->display($field, $to, $names))
                    : new TaskActivityChangeData($field, null, null);
            }
        }

        return $changes;
    }

    /**
     * Null = the row did not touch the field; otherwise the logged value per column (a cleared value is an array holding null).
     *
     * @return array<string, mixed>|null
     */
    private function snapshot(Activity $row, string $bag, TaskActivityFieldEnum $field): ?array
    {
        $values = $row->attributeChangesArray()[$bag] ?? null;

        if (! is_array($values)) {
            return null;
        }

        $present = array_intersect_key($values, array_flip($field->columns()));

        return $present === [] ? null : array_map(fn (mixed $v): mixed => $this->normalize($field, $v), $present);
    }

    private function normalize(TaskActivityFieldEnum $field, mixed $value): mixed
    {
        if (($field === TaskActivityFieldEnum::DueAt || $field === TaskActivityFieldEnum::RemindAt) && is_string($value) && $value !== '') {
            return CarbonImmutable::parse($value)->utc()->toIso8601String();
        }

        return $value;
    }

    /** @param  array<string, mixed>|null  $values */
    private function flatten(?array $values): string
    {
        return json_encode($values === null ? null : array_values($values), JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @param  Collection<string, string>  $names
     */
    private function display(TaskActivityFieldEnum $field, ?array $values, Collection $names): ?string
    {
        $value = $values === null ? null : (array_values($values)[0] ?? null);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $field === TaskActivityFieldEnum::Assignee ? ($names->get($value) ?? __('app.task_activity_former_member')) : $value;
    }

    /**
     * @param  Collection<int, Activity>  $rows
     * @return Collection<string, string>
     */
    private function assigneeNames(Collection $rows): Collection
    {
        $ids = $rows->flatMap(fn (Activity $row): array => [
            $this->snapshot($row, 'attributes', TaskActivityFieldEnum::Assignee)['assigned_membership_id'] ?? null,
            $this->snapshot($row, 'old', TaskActivityFieldEnum::Assignee)['assigned_membership_id'] ?? null,
        ])->filter(fn (mixed $id): bool => is_string($id) && $id !== '')->unique()->values();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        /** @var array<string, string> $names */
        $names = [];

        foreach (TenantMembership::query()->with('user')->whereIn('id', $ids)->get() as $membership) {
            $names[$membership->id] = $membership->display_name;
        }

        return new Collection($names);
    }
}
