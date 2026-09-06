<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Objects\ObjectListItemData;
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
    ) {}

    /**
     * @return LengthAwarePaginator<int, ObjectListItemData>
     */
    public function paginate(Request $request, User $actor): LengthAwarePaginator
    {
        return QueryBuilder::for(CleaningObject::query()->visibleTo($actor))
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
            ->with('client:id,name')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (CleaningObject $object) => ObjectListItemData::fromModel($object));
    }

    public function create(ObjectUpsertData $data): CleaningObject
    {
        return $this->db->transaction(function () use ($data): CleaningObject {
            /** @var array<string, mixed> $attributes */
            $attributes = $data->toArray();
            /** @var CleaningObject $object */
            $object = CleaningObject::create($attributes);

            return $object->load('client');
        });
    }

    public function update(CleaningObject $object, ObjectUpsertData $data): CleaningObject
    {
        return $this->db->transaction(function () use ($object, $data): CleaningObject {
            /** @var array<string, mixed> $attributes */
            $attributes = $data->toArray();
            $object->update($attributes);

            return $object->load('client');
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
}
