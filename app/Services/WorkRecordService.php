<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Objects\ObjectVisitRecordsData;
use App\Data\Objects\ObjectVisitSummaryLineData;
use App\Data\Schedule\JobWorkRecordData;
use App\Data\Schedule\JobWorkRecordItemData;
use App\Data\Schedule\JobWorkRecordPhotoData;
use App\Data\Schedule\WorkRecordAdminStoreData;
use App\Data\Schedule\WorkRecordContextData;
use App\Data\Schedule\WorkRecordItemOptionData;
use App\Data\Schedule\WorkRecordObjectData;
use App\Data\Schedule\WorkRecordStoreData;
use App\Data\Schedule\WorkRecordUpdateData;
use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\ObjectVisitItem;
use App\Models\ScheduledJob;
use App\Models\ScheduledJobItem;
use App\Models\User;
use App\Rules\WorkRecordPerformedAt;
use App\Scopes\TenantScope;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

final readonly class WorkRecordService
{
    public function __construct(
        private DatabaseManager $db,
        private TenantPermissionSnapshot $snapshot,
        private JobService $jobs,
    ) {}

    public function context(User $actor): WorkRecordContextData
    {
        $firms = array_values(array_filter(
            $this->snapshot->forRequest($actor),
            static fn (TenantVisibility $visibility): bool => $visibility->membershipId !== null
                && in_array(PermissionEnum::RecordOwnWork, $visibility->permissions, true),
        ));

        $backdateDays = config()->integer('scheduling.work_records.backdate_days');
        $maxPhotos = config()->integer('scheduling.work_records.photos.max_count');
        $maxPhotoSizeKb = config()->integer('scheduling.work_records.photos.max_size_kb');

        if ($firms === []) {
            return new WorkRecordContextData([], $backdateDays, $maxPhotos, $maxPhotoSizeKb);
        }

        $objects = CleaningObject::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($firms): void {
                foreach ($firms as $firm) {
                    $query->orWhere(fn (Builder $own) => $own
                        ->where('tenant_id', $firm->tenant->id)
                        ->reachableByMemberships([(string) $firm->membershipId]));
                }
            })
            ->whereHas('visitItems', fn (Builder $query) => $query->withoutGlobalScope(TenantScope::class))
            ->with(['visitItems' => fn (Relation $query) => $query->withoutGlobalScope(TenantScope::class)])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return new WorkRecordContextData(
            objects: array_values($objects
                ->map(fn (CleaningObject $object): WorkRecordObjectData => new WorkRecordObjectData(
                    tenant_id: $object->tenant_id,
                    object_id: $object->id,
                    name: $object->name,
                    street: $object->street,
                    city: $object->city,
                    items: $object->visitItems
                        ->map(fn (ObjectVisitItem $item): WorkRecordItemOptionData => new WorkRecordItemOptionData($item->id, $item->name))
                        ->values()
                        ->all(),
                ))
                ->all()),
            backdate_days: $backdateDays,
            max_photos: $maxPhotos,
            max_photo_size_kb: $maxPhotoSizeKb,
        );
    }

    public function record(WorkRecordStoreData $data, User $actor): ScheduledJob
    {
        $membershipId = $actor->activeMembershipId();
        $performedAt = $this->parse($data->performed_at);

        try {
            return $this->db->transaction(function () use ($data, $actor, $membershipId, $performedAt): ScheduledJob {
                $this->lockClientUuid($data->client_uuid);

                $existing = $this->findByClientUuid($data->client_uuid);

                if ($existing !== null) {
                    return $this->assertReplayOf($existing, $membershipId);
                }

                $violation = WorkRecordPerformedAt::windowViolation($performedAt, config()->integer('scheduling.work_records.backdate_days'));

                if ($violation !== null) {
                    throw ValidationException::withMessages(['performed_at' => [$violation]]);
                }

                $object = CleaningObject::query()->whereKey($data->cleaning_object_id)->first();

                if ($object === null || ! $object->is_active) {
                    throw ValidationException::withMessages(['cleaning_object_id' => [__('app.job_object_not_visible')]]);
                }

                Gate::forUser($actor)->authorize('recordWork', [ScheduledJob::class, $object]);

                return $this->createRecord(
                    $object,
                    (string) $membershipId,
                    $performedAt,
                    $data->item_ids,
                    $data->note,
                    $data->client_uuid,
                );
            });
        } catch (UniqueConstraintViolationException $e) {
            $existing = $this->findByClientUuid($data->client_uuid);

            if ($existing === null) {
                throw $e;
            }

            return $this->assertReplayOf($existing, $membershipId);
        }
    }

    public function recordFor(CleaningObject $object, WorkRecordAdminStoreData $data): ScheduledJob
    {
        $performedAt = $this->parse($data->performed_at);

        return $this->db->transaction(fn (): ScheduledJob => $this->createRecord(
            $object,
            $data->tenant_membership_id,
            $performedAt,
            $data->item_ids,
            $data->note,
            null,
        ));
    }

    public function correct(ScheduledJob $job, WorkRecordUpdateData $data, User $actor): ScheduledJob
    {
        $performedAt = $this->parse($data->performed_at);

        return $this->db->transaction(function () use ($job, $data, $actor, $performedAt): ScheduledJob {
            $locked = ScheduledJob::query()->lockForUpdate()->findOrFail($job->id);

            Gate::forUser($actor)->authorize('updateWorkRecord', $locked);

            $keepIds = $this->normaliseIds($data->keep_item_ids);
            $addIds = $this->normaliseIds($data->add_item_ids);

            $current = $locked->items()->get()->keyBy('id');
            $before = $this->itemSnapshot($current);

            if (array_diff($keepIds, $current->keys()->all()) !== []) {
                throw ValidationException::withMessages(['keep_item_ids' => [__('app.work_record_keep_invalid')]]);
            }

            $keptLinks = array_values(array_filter(array_map(
                static fn (ScheduledJobItem $item): ?string => $item->object_visit_item_id,
                $current->only($keepIds)->all(),
            )));

            /** @var array<string, ObjectVisitItem> $toAdd */
            $toAdd = ObjectVisitItem::query()
                ->where('cleaning_object_id', $locked->cleaning_object_id)
                ->whereIn('id', $addIds)
                ->get()
                ->keyBy('id')
                ->all();

            if (count($toAdd) !== count($addIds)) {
                throw ValidationException::withMessages(['add_item_ids' => [__('app.work_record_items_invalid')]]);
            }

            if (array_intersect($addIds, $keptLinks) !== []) {
                throw ValidationException::withMessages(['add_item_ids' => [__('app.work_record_item_already_present')]]);
            }

            $current->except($keepIds)->each(fn (ScheduledJobItem $item): ?bool => $item->delete());

            foreach ($addIds as $id) {
                $this->snapshotItem($locked, $toAdd[$id]);
            }

            $locked->update([
                'assigned_membership_id' => $data->tenant_membership_id,
                'completed_at' => $performedAt,
                ...$this->timeAttributes($performedAt),
                'note' => $data->note,
            ]);

            $after = $this->itemSnapshot($locked->items()->get());

            activity()
                ->performedOn($locked)
                ->causedBy($actor)
                ->event('work_record_corrected')
                ->withProperties(['items_before' => $before, 'items_after' => $after])
                ->log('work_record_corrected');

            return $locked;
        });
    }

    public function delete(ScheduledJob $job, User $actor): void
    {
        $this->db->transaction(function () use ($job, $actor): void {
            $locked = ScheduledJob::query()->lockForUpdate()->findOrFail($job->id);

            Gate::forUser($actor)->authorize('deleteWorkRecord', $locked);

            $locked->delete();
        });
    }

    public function overview(CleaningObject $object, string $month, User $actor): ObjectVisitRecordsData
    {
        $timezone = config()->string('scheduling.timezone');
        $parsed = CarbonImmutable::createFromFormat('!Y-m', $month, $timezone);
        $start = ($parsed instanceof CarbonImmutable ? $parsed : CarbonImmutable::now($timezone))->startOfMonth();

        $jobs = ScheduledJob::query()
            ->where('cleaning_object_id', $object->id)
            ->where('type', JobTypeEnum::OneOff)
            ->where('status', JobStatusEnum::Completed)
            ->whereHas('items')
            ->whereBetween('scheduled_date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->with(['items', 'assignedMembership.user', 'media'])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        $lines = [];
        $totalCents = 0;

        foreach ($jobs->flatMap(fn (ScheduledJob $job) => $job->items) as $item) {
            $price = number_format((float) $item->unit_price, 2, '.', '');
            $key = $item->name."\0".$price;
            $cents = $this->cents($price);
            $totalCents += $cents;
            $lines[$key] ??= ['name' => $item->name, 'price' => $price, 'count' => 0, 'cents' => 0];
            $lines[$key]['count']++;
            $lines[$key]['cents'] += $cents;
        }

        usort($lines, static fn (array $a, array $b): int => [$a['name'], $a['price']] <=> [$b['name'], $b['price']]);

        $canAdd = Gate::forUser($actor)->allows('createWorkRecord', [ScheduledJob::class, $object]);

        return new ObjectVisitRecordsData(
            month: $start->format('Y-m'),
            summary: array_map(
                static fn (array $line): ObjectVisitSummaryLineData => new ObjectVisitSummaryLineData(
                    $line['name'],
                    $line['price'],
                    $line['count'],
                    self::money($line['cents']),
                ),
                $lines,
            ),
            total: self::money($totalCents),
            records: $jobs->map(fn (ScheduledJob $job): JobWorkRecordData => $this->present($job, $actor))->values()->all(),
            can_add: $canAdd,
            members: $canAdd ? $this->jobs->assignableMembershipOptions() : [],
        );
    }

    public function present(ScheduledJob $job, User $actor): JobWorkRecordData
    {
        $job->loadMissing(['items', 'assignedMembership.user', 'media']);

        $withPrice = $actor->can(PermissionEnum::ViewAllObjects->value);
        $gate = Gate::forUser($actor);

        return new JobWorkRecordData(
            job_id: $job->id,
            performed_at: ($job->completed_at ?? $job->created_at)->toIso8601ZuluString(),
            tenant_membership_id: $job->assigned_membership_id,
            recorded_by_name: $job->assignedMembership?->display_name,
            note: $job->note,
            items: $job->items
                ->map(fn (ScheduledJobItem $item): JobWorkRecordItemData => JobWorkRecordItemData::fromModel($item, $withPrice))
                ->values()
                ->all(),
            photos: $job->getMedia(ScheduledJob::PHOTOS_COLLECTION)
                ->map(fn (SpatieMedia $media): JobWorkRecordPhotoData => new JobWorkRecordPhotoData(
                    id: $media->uuid,
                    name: $media->name,
                    url: route('jobs.photos.show', ['job' => $job->id, 'photo' => $media->uuid], absolute: false),
                ))
                ->values()
                ->all(),
            can_update: $gate->allows('updateWorkRecord', $job),
            can_delete: $gate->allows('deleteWorkRecord', $job),
        );
    }

    /**
     * @param  array<int, string>  $ids
     * @return list<string>
     */
    private function normaliseIds(array $ids): array
    {
        return array_values(array_unique(array_map(strtolower(...), $ids)));
    }

    /** @param list<string> $itemIds */
    private function createRecord(
        CleaningObject $object,
        string $membershipId,
        CarbonImmutable $performedAt,
        array $itemIds,
        ?string $note,
        ?string $clientUuid,
    ): ScheduledJob {
        $itemIds = $this->normaliseIds($itemIds);

        /** @var array<string, ObjectVisitItem> $items */
        $items = ObjectVisitItem::query()
            ->where('cleaning_object_id', $object->id)
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id')
            ->all();

        if (count($items) !== count($itemIds)) {
            throw ValidationException::withMessages(['item_ids' => [__('app.work_record_items_invalid')]]);
        }

        $job = ScheduledJob::query()->create([
            'tenant_id' => $object->tenant_id,
            'cleaning_object_id' => $object->id,
            'assigned_membership_id' => $membershipId,
            'type' => JobTypeEnum::OneOff,
            'status' => JobStatusEnum::Completed,
            'completed_at' => $performedAt,
            'note' => $note,
            'client_uuid' => $clientUuid,
            ...$this->timeAttributes($performedAt),
        ]);

        foreach ($itemIds as $id) {
            $this->snapshotItem($job, $items[$id]);
        }

        return $job->load('items');
    }

    private function snapshotItem(ScheduledJob $job, ObjectVisitItem $item): void
    {
        $job->items()->create([
            'tenant_id' => $job->tenant_id,
            'object_visit_item_id' => $item->id,
            'name' => $item->name,
            'unit_price' => $item->price,
        ]);
    }

    /** @return array{scheduled_date: string, start_time: string} */
    private function timeAttributes(CarbonImmutable $performedAt): array
    {
        $local = $performedAt->setTimezone(config()->string('scheduling.timezone'));

        return ['scheduled_date' => $local->toDateString(), 'start_time' => $local->format('H:i:s')];
    }

    private function parse(string $value): CarbonImmutable
    {
        return WorkRecordPerformedAt::parse($value)
            ?? throw ValidationException::withMessages(['performed_at' => [__('app.work_record_performed_at_invalid')]]);
    }

    private function lockClientUuid(string $clientUuid): void
    {
        $this->db->select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [current_tenant_id().':'.strtolower($clientUuid)]);
    }

    private function findByClientUuid(string $clientUuid): ?ScheduledJob
    {
        return ScheduledJob::withTrashed()->where('client_uuid', strtolower($clientUuid))->first();
    }

    private function assertReplayOf(ScheduledJob $existing, ?string $membershipId): ScheduledJob
    {
        if ($existing->assigned_membership_id !== $membershipId) {
            throw ValidationException::withMessages(['client_uuid' => [__('app.work_record_client_uuid_taken')]]);
        }

        return $existing;
    }

    /**
     * @param  iterable<ScheduledJobItem>  $items
     * @return list<array{name: string, unit_price: string}>
     */
    private function itemSnapshot(iterable $items): array
    {
        $snapshot = [];

        foreach ($items as $item) {
            $snapshot[] = ['name' => $item->name, 'unit_price' => number_format((float) $item->unit_price, 2, '.', '')];
        }

        return $snapshot;
    }

    private function cents(string $price): int
    {
        return (int) str_replace('.', '', $price);
    }

    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
