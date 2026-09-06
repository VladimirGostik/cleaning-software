<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Clients\ClientContactData;
use App\Data\Clients\ClientListItemData;
use App\Data\Clients\ClientUpsertData;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ClientContact;
use App\Utils\AllowedFilter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\DataCollection;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ClientService
{
    public function __construct(
        private DatabaseManager $db,
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

            $this->syncContacts($client, $data->contacts);

            return $client->load('contacts');
        });
    }

    public function update(Client $client, ClientUpsertData $data): Client
    {
        return $this->db->transaction(function () use ($client, $data): Client {
            /** @var array<string, mixed> $attributes */
            $attributes = $data->except('contacts')->toArray();
            $client->update($attributes);

            $this->syncContacts($client, $data->contacts);

            return $client->load('contacts');
        });
    }

    /**
     * Soft-deletes the client, its contacts, and its objects (D1) — an object has no
     * bulk-deactivate affordance, and blocking destroy while active objects exist would
     * not remove the orphan case, only add a step.
     */
    public function delete(Client $client): void
    {
        $client->load(['contacts', 'objects']);

        $this->db->transaction(function () use ($client): void {
            $client->contacts->each(fn (ClientContact $contact) => $contact->delete());
            $client->objects->each(fn (CleaningObject $object) => $object->delete());
            $client->delete();
        });
    }

    /**
     * @param  DataCollection<int, ClientContactData>  $contacts
     */
    private function syncContacts(Client $client, DataCollection $contacts): void
    {
        $contactItems = $contacts->toCollection();

        /** @var Collection<int, ClientContact> $existingContacts */
        $existingContacts = $client->contacts()->get();
        $existingById = $existingContacts->keyBy('id');

        /** @var list<string> $incomingIds */
        $incomingIds = $contactItems
            ->filter(fn (ClientContactData $c) => $c->id !== null)
            ->pluck('id')
            ->all();

        foreach ($incomingIds as $incomingId) {
            if (! $existingById->has($incomingId)) {
                throw ValidationException::withMessages([
                    'contacts' => [__('app.client_contact_invalid')],
                ]);
            }
        }

        foreach ($existingContacts as $existing) {
            if (! in_array($existing->id, $incomingIds, true)) {
                $existing->delete();
            }
        }

        // Every remaining/new contact gets its `is_primary` set explicitly below, so no
        // separate "reset all to false" pass is needed first.
        $hasPrimary = $contactItems->contains(fn (ClientContactData $c) => $c->is_primary);

        foreach ($contactItems as $index => $contactData) {
            $isPrimary = $contactData->is_primary || (! $hasPrimary && $index === 0);

            if ($contactData->id !== null) {
                $existingById->get($contactData->id)?->update([
                    'name' => $contactData->name,
                    'position' => $contactData->position,
                    'email' => $contactData->email,
                    'phone' => $contactData->phone,
                    'is_primary' => $isPrimary,
                ]);

                continue;
            }

            $client->contacts()->create([
                'name' => $contactData->name,
                'position' => $contactData->position,
                'email' => $contactData->email,
                'phone' => $contactData->phone,
                'is_primary' => $isPrimary,
            ]);
        }
    }
}
