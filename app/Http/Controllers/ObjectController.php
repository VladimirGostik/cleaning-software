<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Clients\ClientOptionData;
use App\Data\Objects\ObjectDetailData;
use App\Data\Objects\ObjectUpsertData;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\ObjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class ObjectController extends Controller
{
    public function __construct(private readonly ObjectService $objects) {}

    #[Authorize('viewAny', CleaningObject::class)]
    #[NavItem(label: 'app.objects', route: 'objects.index', icon: 'BuildingOffice2Icon', permission: PermissionEnum::ViewObjects->value, order: 31)]
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Objects/Index', [
            'objects' => $this->objects->paginate($request, $actor),
            'filters' => $request->query(),
            'filterOptions' => [
                'clients' => $this->clientOptions($actor),
            ],
        ]);
    }

    #[Authorize('view', 'object')]
    public function show(CleaningObject $object, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $object->load('client');

        return Inertia::render('Objects/Show', [
            'object' => ObjectDetailData::fromModel($object),
            'clients' => $actor->can('update', $object) ? $this->clientOptions($actor) : [],
        ]);
    }

    #[Authorize('create', CleaningObject::class)]
    public function store(ObjectUpsertData $data): RedirectResponse
    {
        $object = $this->objects->create($data);

        return to_route('objects.show', $object)->with('success', __('app.object_created'));
    }

    #[Authorize('update', 'object')]
    public function update(ObjectUpsertData $data, CleaningObject $object): RedirectResponse
    {
        $this->objects->update($object, $data);

        return to_route('objects.show', $object)->with('success', __('app.object_updated'));
    }

    #[Authorize('delete', 'object')]
    public function deactivate(CleaningObject $object): RedirectResponse
    {
        $this->objects->deactivate($object);

        return to_route('objects.show', $object)->with('success', __('app.object_deactivated'));
    }

    #[Authorize('update', 'object')]
    public function reactivate(CleaningObject $object): RedirectResponse
    {
        $this->objects->reactivate($object);

        return to_route('objects.show', $object)->with('success', __('app.object_reactivated'));
    }

    /** @return array<int, ClientOptionData> */
    private function clientOptions(User $actor): array
    {
        if (! $actor->can(PermissionEnum::ViewAllObjects->value)) {
            return [];
        }

        return Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => ClientOptionData::fromModel($client))
            ->all();
    }
}
