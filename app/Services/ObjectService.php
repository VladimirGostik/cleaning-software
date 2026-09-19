<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Objects\ObjectListItemData;
use App\Data\Objects\ObjectOptionData;
use App\Data\Objects\ObjectUpsertData;
use App\Models\CleaningObject;
use App\Models\User;
use App\Utils\AllowedFilter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ObjectService
{
    public function __construct(
        private DatabaseManager $db,
        private ContactCollectionSynchronizer $contacts,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ObjectListItemData>
     */
    public function paginate(Request $request, User $actor): LengthAwarePaginator
    {
        $includeContacts = $actor->can('viewContacts', CleaningObject::class);

        $query = QueryBuilder::for(CleaningObject::query()->visibleTo($actor))
            ->allowedFilters(
                AllowedFilter::search(['name', 'street', 'city']),
                AllowedFilter::dynamic('name'),
                AllowedFilter::dynamic('type'),
                AllowedFilter::dynamic('client_id')->uuid(),
                AllowedFilter::dynamic('is_active')->boolean(),
                AllowedFilter::dynamic('city'),
                AllowedFilter::dynamic('created_at')->date(),
            )
            ->allowedSorts('name', 'type', 'city', 'is_active', 'created_at')
            ->defaultSort('name')
            ->with('client:id,name');

        if ($includeContacts) {
            $query->withCount('contacts')->with('primaryContact');
        }

        return $query
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (CleaningObject $object) => ObjectListItemData::fromModel($object, $includeContacts));
    }

    public function create(ObjectUpsertData $data, User $actor): CleaningObject
    {
        return $this->db->transaction(function () use ($data, $actor): CleaningObject {
            /** @var array<string, mixed> $attributes */
            $attributes = $data->except('contacts')->toArray();
            /** @var CleaningObject $object */
            $object = CleaningObject::create($attributes);

            if ($actor->can('viewContacts', CleaningObject::class)) {
                $this->contacts->sync($object->contacts(), $data->contacts->items(), 'app.object_contact_invalid');
            }

            return $object->load(['client', 'contacts']);
        });
    }

    public function update(CleaningObject $object, ObjectUpsertData $data, User $actor): CleaningObject
    {
        return $this->db->transaction(function () use ($object, $data, $actor): CleaningObject {
            /** @var array<string, mixed> $attributes */
            $attributes = $data->except('contacts')->toArray();
            $object->update($attributes);

            // Gated actor's `contacts` payload is ignored — existing rows are left intact,
            // never wiped by a form that never showed them (write-side of the Q1 gate).
            if ($actor->can('viewContacts', CleaningObject::class)) {
                $this->contacts->sync($object->contacts(), $data->contacts->items(), 'app.object_contact_invalid');
            }

            return $object->load(['client', 'contacts']);
        });
    }

    public function deactivate(CleaningObject $object): void
    {
        $this->db->transaction(function () use ($object): void {
            $object->update(['is_active' => false]);
        });
    }

    public function reactivate(CleaningObject $object): void
    {
        $this->db->transaction(function () use ($object): void {
            $object->update(['is_active' => true]);
        });
    }

    /**
     * Active objects reachable by `$actor` — used for the schedule job-creation object picker.
     *
     * @return array<int, ObjectOptionData>
     */
    public function optionsVisibleTo(User $actor): array
    {
        return CleaningObject::query()
            ->visibleTo($actor)
            ->where('is_active', true)
            ->with('client:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (CleaningObject $object) => ObjectOptionData::fromModel($object))
            ->all();
    }
}
