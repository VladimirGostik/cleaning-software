<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Clients\ClientDetailData;
use App\Data\Clients\ClientIndexFilterData;
use App\Data\Clients\ClientListItemData;
use App\Data\Clients\ClientStoreData;
use App\Data\Clients\ClientUpdateData;
use App\Enums\ClientType;
use App\Models\Client;
use App\Services\ClientService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\PaginatedDataCollection;

final class ClientController extends Controller
{
    public function __construct(private readonly ClientService $clients) {}

    public function index(ClientIndexFilterData $filter): Response
    {
        $this->authorize('viewAny', Client::class);

        return Inertia::render('Clients/Index', [
            'clients' => ClientListItemData::collect($this->clients->paginate($filter), PaginatedDataCollection::class),
            'filters' => $filter,
            'types' => ClientType::options(),
        ]);
    }

    public function show(Client $client): Response
    {
        $this->authorize('view', $client);

        $client->load('contacts');

        return Inertia::render('Clients/Show', [
            'client' => ClientDetailData::from($client),
        ]);
    }

    public function store(ClientStoreData $data): RedirectResponse
    {
        $this->authorize('create', Client::class);

        $this->clients->create($data);

        return to_route('clients.index')->with('flash.success', __('app.clients.created'));
    }

    public function update(ClientUpdateData $data, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $this->clients->update($client, $data);

        return to_route('clients.show', $client)->with('flash.success', __('app.clients.updated'));
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $this->clients->delete($client);

        return to_route('clients.index')->with('flash.success', __('app.clients.deleted'));
    }
}
