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
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkBreakdown;
use App\Services\ObjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

final class ObjectController extends Controller
{
    public function __construct(private readonly ObjectService $objects) {}

    #[Authorize('viewAny', CleaningObject::class)]
    public function index(ObjectIndexFilterData $filter, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $clients = $actor->can(PermissionEnum::ViewAllObjects->value)
            ? Client::query()->orderBy('name')->get(['id', 'name'])->all()
            : [];

        return Inertia::render('Objects/Index', [
            'objects' => ObjectListItemData::collect($this->objects->paginate($filter, $actor), PaginatedDataCollection::class),
            'filters' => $filter,
            'types' => ObjectTypeEnum::options(),
            'clients' => $clients,
        ]);
    }

    #[Authorize('view', 'object')]
    public function show(CleaningObject $object, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $object->load(['client', 'workBreakdowns.tasks']);

        $clients = $actor->can('update', $object)
            ? Client::query()->orderBy('name')->get(['id', 'name'])->all()
            : [];

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
    public function deactivate(CleaningObject $object): RedirectResponse
    {
        $this->objects->deactivate($object);

        return to_route('objects.show', $object)->with('flash.success', __('app.objects.deactivated'));
    }
}
