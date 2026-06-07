<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Objects\ObjectIndexFilterData;
use App\Data\Objects\ObjectStoreData;
use App\Data\Objects\ObjectUpdateData;
use App\Models\CleaningObject;
use Illuminate\Database\DatabaseManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ObjectService
{
    public function __construct(
        private DatabaseManager $db,
    ) {}

    /**
     * @return LengthAwarePaginator<CleaningObject>
     */
    public function paginate(ObjectIndexFilterData $filter): LengthAwarePaginator
    {
        return QueryBuilder::for(CleaningObject::class)
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('client_id'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('name'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('name')
            ->with('client:id,name')
            ->paginate($filter->per_page)
            ->appends(request()->query());
    }

    public function create(ObjectStoreData $data): CleaningObject
    {
        return $this->db->transaction(function () use ($data): CleaningObject {
            $object = CleaningObject::create($data->toArray());

            return $object->load('client');
        });
    }

    public function update(CleaningObject $object, ObjectUpdateData $data): CleaningObject
    {
        return $this->db->transaction(function () use ($object, $data): CleaningObject {
            $object->update($data->toArray());

            return $object->load('client');
        });
    }

    public function delete(CleaningObject $object): void
    {
        $this->db->transaction(function () use ($object): void {
            $object->delete();
        });
    }
}
