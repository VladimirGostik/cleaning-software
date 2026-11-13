<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Objects\ObjectVisitItemData;
use App\Data\Objects\ObjectVisitItemInputData;
use App\Data\Objects\ObjectVisitItemsSyncData;
use App\Models\CleaningObject;
use App\Models\ObjectVisitItem;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ObjectVisitItemService
{
    public function __construct(private DatabaseManager $db) {}

    /** @return list<ObjectVisitItemData> */
    public function listFor(CleaningObject $object): array
    {
        return array_values($object->visitItems()
            ->get()
            ->map(fn (ObjectVisitItem $item): ObjectVisitItemData => ObjectVisitItemData::fromModel($item))
            ->all());
    }

    public function sync(CleaningObject $object, ObjectVisitItemsSyncData $data): void
    {
        $rows = array_values($data->items->toCollection()->all());

        $this->db->transaction(function () use ($object, $rows): void {
            CleaningObject::query()->whereKey($object->id)->lockForUpdate()->firstOrFail();

            /** @var array<string, ObjectVisitItem> $live */
            $live = ObjectVisitItem::query()
                ->where('cleaning_object_id', $object->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id')
                ->all();

            $this->assertOwnIds($rows, array_map('strval', array_keys($live)));

            $keptIds = array_values(array_filter(array_map(static fn (ObjectVisitItemInputData $row): ?string => $row->id, $rows)));

            foreach ($live as $item) {
                if (! in_array($item->id, $keptIds, true)) {
                    $item->delete();
                }
            }

            foreach ($rows as $row) {
                if ($row->id !== null && $live[$row->id]->name !== trim($row->name)) {
                    ObjectVisitItem::query()->whereKey($row->id)->update(['name' => Str::uuid()->toString()]);
                }
            }

            foreach ($rows as $position => $row) {
                $attributes = [
                    'name' => trim($row->name),
                    'price' => number_format((float) $row->price, 2, '.', ''),
                    'position' => $position,
                ];

                if ($row->id === null) {
                    ObjectVisitItem::query()->create([...$attributes, 'tenant_id' => $object->tenant_id, 'cleaning_object_id' => $object->id]);

                    continue;
                }

                $live[$row->id]->update($attributes);
            }
        });
    }

    /**
     * @param  list<ObjectVisitItemInputData>  $rows
     * @param  list<string>  $liveIds
     */
    private function assertOwnIds(array $rows, array $liveIds): void
    {
        $errors = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            if ($row->id === null) {
                continue;
            }

            if (! in_array($row->id, $liveIds, true) || in_array($row->id, $seen, true)) {
                $errors["items.$index.id"] = [__('app.object_visit_item_foreign')];

                continue;
            }

            $seen[] = $row->id;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
