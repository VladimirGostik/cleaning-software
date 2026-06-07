<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Clients\ClientDetailData;
use App\Data\Clients\ClientIndexFilterData;
use App\Data\Clients\ClientListItemData;
use App\Data\Clients\ClientStoreData;
use App\Data\Clients\ClientUpdateData;
use App\Data\Objects\ObjectListItemData;
use App\Enums\ClientTypeEnum;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

final class ClientController extends Controller
{
    public function __construct(private readonly ClientService $clients) {}

    #[Authorize('viewAny', Client::class)]
    public function index(ClientIndexFilterData $filter): Response
    {
        return Inertia::render('Clients/Index', [
            'clients' => ClientListItemData::collect($this->clients->paginate($filter), PaginatedDataCollection::class),
            'filters' => $filter,
            'types' => ClientTypeEnum::options(),
        ]);
    }

    #[Authorize('view', 'client')]
    public function show(Client $client): Response
    {
        $client->load('contacts');

        $objects = $client->objects()->with('client')->get();

        return Inertia::render('Clients/Show', [
            'client' => ClientDetailData::from($client),
            'objects' => ObjectListItemData::collect($objects, DataCollection::class),
        ]);
    }

    #[Authorize('create', Client::class)]
    public function store(ClientStoreData $data): RedirectResponse
    {
        $this->clients->create($data);

        return to_route('clients.index')->with('flash.success', __('app.clients.created'));
    }

    #[Authorize('update', 'client')]
    public function update(ClientUpdateData $data, Client $client): RedirectResponse
    {
        $this->clients->update($client, $data);

        return to_route('clients.show', $client)->with('flash.success', __('app.clients.updated'));
    }

    #[Authorize('delete', 'client')]
    public function destroy(Client $client): RedirectResponse
    {
        $this->clients->delete($client);

        return to_route('clients.index')->with('flash.success', __('app.clients.deleted'));
    }
}
