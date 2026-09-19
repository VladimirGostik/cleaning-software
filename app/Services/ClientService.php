<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Clients\ClientListItemData;
use App\Data\Clients\ClientUpsertData;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ObjectContact;
use App\Utils\AllowedFilter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ClientService
{
    public function __construct(
        private DatabaseManager $db,
        private ContactCollectionSynchronizer $contacts,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ClientListItemData>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Client::query())
            ->allowedFilters(
                AllowedFilter::search(['name', 'ico']),
                AllowedFilter::dynamic('name'),
                AllowedFilter::dynamic('type'),
                AllowedFilter::dynamic('city'),
                AllowedFilter::dynamic('ico'),
                AllowedFilter::dynamic('created_at')->date(),
            )
            ->allowedSorts('name', 'type', 'city', 'ico', 'created_at')
            ->defaultSort('name')
            ->withCount(['contacts', 'objects'])
            ->with('primaryContact')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (Client $client) => ClientListItemData::fromModel($client));
    }

    public function create(ClientUpsertData $data): Client
    {
        return $this->db->transaction(function () use ($data): Client {
            /** @var array<string, mixed> $attributes */
            $attributes = $data->except('contacts')->toArray();
            /** @var Client $client */
            $client = Client::create($attributes);

            $this->contacts->sync($client->contacts(), $data->contacts->items(), 'app.client_contact_invalid');

            return $client->load('contacts');
        });
    }

    public function update(Client $client, ClientUpsertData $data): Client
    {
        return $this->db->transaction(function () use ($client, $data): Client {
            /** @var array<string, mixed> $attributes */
            $attributes = $data->except('contacts')->toArray();
            $client->update($attributes);

            $this->contacts->sync($client->contacts(), $data->contacts->items(), 'app.client_contact_invalid');

            return $client->load('contacts');
        });
    }

    /**
     * Soft-deletes the client, its contacts, its objects (D1), and each of those objects'
     * own contacts — an object has no bulk-deactivate affordance, and blocking destroy while
     * active objects exist would not remove the orphan case, only add a step.
     */
    public function delete(Client $client): void
    {
        $client->load(['contacts', 'objects.contacts']);

        $this->db->transaction(function () use ($client): void {
            $client->contacts->each(fn (ClientContact $contact) => $contact->delete());
            $client->objects->each(function (CleaningObject $object): void {
                $object->contacts->each(fn (ObjectContact $contact) => $contact->delete());
                $object->delete();
            });
            $client->delete();
        });
    }
}
