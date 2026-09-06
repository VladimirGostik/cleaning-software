<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Clients\ClientDetailData;
use App\Data\Clients\ClientUpsertData;
use App\Data\Objects\ObjectListItemData;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Navigation\NavItem;
use App\Services\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class ClientController extends Controller
{
    public function __construct(private readonly ClientService $clients) {}

    #[Authorize('viewAny', Client::class)]
    #[NavItem(label: 'app.clients', route: 'clients.index', icon: 'UserGroupIcon', permission: PermissionEnum::ViewClients->value, order: 30)]
    public function index(Request $request): Response
    {
        return Inertia::render('Clients/Index', [
            'clients' => $this->clients->paginate($request),
            'filters' => $request->query(),
        ]);
    }

    #[Authorize('view', 'client')]
    public function show(Client $client): Response
    {
        $client->load('contacts');

        $objects = $client->objects()->with('client:id,name')->orderBy('name')->get();

        return Inertia::render('Clients/Show', [
            'client' => ClientDetailData::fromModel($client),
            'objects' => $objects->map(fn (CleaningObject $object) => ObjectListItemData::fromModel($object))->all(),
        ]);
    }

    #[Authorize('create', Client::class)]
    public function store(ClientUpsertData $data): RedirectResponse
    {
        $this->clients->create($data);

        return to_route('clients.index')->with('success', __('app.client_created'));
    }

    #[Authorize('update', 'client')]
    public function update(ClientUpsertData $data, Client $client): RedirectResponse
    {
        $this->clients->update($client, $data);

        return to_route('clients.show', $client)->with('success', __('app.client_updated'));
    }

    #[Authorize('delete', 'client')]
    public function destroy(Client $client): RedirectResponse
    {
        $this->clients->delete($client);

        return to_route('clients.index')->with('success', __('app.client_deleted'));
    }
}
