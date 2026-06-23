<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Objects\ObjectDetailData;
use App\Data\Objects\ObjectIndexFilterData;
use App\Data\Objects\ObjectListItemData;
use App\Data\Objects\ObjectStoreData;
use App\Data\Objects\ObjectUpdateData;
use App\Data\Schedule\WorkBreakdownDetailData;
use App\Enums\ObjectTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\WorkBreakdown;
use App\Services\ObjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

final class ObjectController extends Controller
{
    public function __construct(private readonly ObjectService $objects) {}

    #[Authorize('viewAny', CleaningObject::class)]
    public function index(ObjectIndexFilterData $filter): Response
    {
        $clients = Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();

        return Inertia::render('Objects/Index', [
            'objects' => ObjectListItemData::collect($this->objects->paginate($filter), PaginatedDataCollection::class),
            'filters' => $filter,
            'types' => ObjectTypeEnum::options(),
            'clients' => $clients,
        ]);
    }

    #[Authorize('view', 'object')]
    public function show(CleaningObject $object): Response
    {
        $object->load(['client', 'workBreakdowns.tasks']);

        $clients = Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();

        return Inertia::render('Objects/Show', [
            'object' => ObjectDetailData::fromModel($object),
            'clients' => $clients,
            'workBreakdowns' => WorkBreakdownDetailData::collect(
                $object->workBreakdowns->map(fn (WorkBreakdown $wb) => WorkBreakdownDetailData::fromModel($wb)),
                DataCollection::class,
            ),
        ]);
    }

    #[Authorize('create', CleaningObject::class)]
    public function store(ObjectStoreData $data): RedirectResponse
    {
        $this->objects->create($data);

        return to_route('objects.index')->with('flash.success', __('app.objects.created'));
    }

    #[Authorize('update', 'object')]
    public function update(ObjectUpdateData $data, CleaningObject $object): RedirectResponse
    {
        $this->objects->update($object, $data);

        return to_route('objects.show', $object)->with('flash.success', __('app.objects.updated'));
    }

    #[Authorize('delete', 'object')]
    public function destroy(CleaningObject $object): RedirectResponse
    {
        $this->objects->delete($object);

        return to_route('objects.index')->with('flash.success', __('app.objects.deleted'));
    }
}
