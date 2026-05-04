<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Clients\ClientContactData;
use App\Data\Clients\ClientIndexFilterData;
use App\Data\Clients\ClientStoreData;
use App\Data\Clients\ClientUpdateData;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\DataCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ClientService
{
    public function __construct(
        private DatabaseManager $db,
    ) {}

    /**
     * @return LengthAwarePaginator<Client>
     */
    public function paginate(ClientIndexFilterData $filter): LengthAwarePaginator
    {
        return QueryBuilder::for(Client::class)
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::exact('type'),
            )
            ->allowedSorts(
                AllowedSort::field('name'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('name')
            ->withCount('contacts')
            ->paginate($filter->per_page)
            ->appends(request()->query());
    }

    public function create(ClientStoreData $data): Client
    {
        return $this->db->transaction(function () use ($data): Client {
            try {
                $client = Client::create($data->except('contacts')->toArray());
            } catch (QueryException $e) {
                $this->handleUniqueIcoViolation($e);
            }

            $this->syncContacts($client, $data->contacts);

            return $client->load('contacts');
        });
    }

    public function update(Client $client, ClientUpdateData $data): Client
    {
        return $this->db->transaction(function () use ($client, $data): Client {
            try {
                $client->update($data->except('contacts')->toArray());
            } catch (QueryException $e) {
                $this->handleUniqueIcoViolation($e);
            }

            $this->syncContacts($client, $data->contacts);

            return $client->load('contacts');
        });
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }

    /**
     * @param  DataCollection<int, ClientContactData>  $contacts
     */
    private function syncContacts(Client $client, DataCollection $contacts): void
    {
        $contactItems = $contacts->toCollection();

        $hasPrimary = $contactItems->contains(fn (ClientContactData $c) => $c->is_primary);

        $incomingIds = $contactItems
            ->filter(fn (ClientContactData $c) => $c->id !== null)
            ->pluck('id')
            ->all();

        /** @var Collection<int, ClientContact> $existingContacts */
        $existingContacts = $client->contacts()->get();
        $existingById = $existingContacts->keyBy('id');

        // Soft-delete contacts no longer in the payload
        foreach ($existingContacts as $existing) {
            if (! in_array($existing->id, $incomingIds, true)) {
                $existing->delete();
            }
        }

        // If any incoming contact is primary, reset all others first
        if ($hasPrimary) {
            $client->contacts()->update(['is_primary' => false]);
        }

        foreach ($contactItems as $contactData) {
            if ($contactData->id !== null) {
                $existing = $existingById->get($contactData->id);

                if ($existing !== null) {
                    $existing->update([
                        'name' => $contactData->name,
                        'position' => $contactData->position,
                        'email' => $contactData->email,
                        'phone' => $contactData->phone,
                        'is_primary' => $contactData->is_primary,
                    ]);
                }
            } else {
                $client->contacts()->create([
                    'tenant_id' => $client->tenant_id,
                    'name' => $contactData->name,
                    'position' => $contactData->position,
                    'email' => $contactData->email,
                    'phone' => $contactData->phone,
                    'is_primary' => $contactData->is_primary,
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function handleUniqueIcoViolation(QueryException $e): never
    {
        $message = $e->getMessage();

        // PostgreSQL: "clients_tenant_ico_unique"
        // SQLite: "clients.tenant_id, clients.ico" or "clients.ico"
        if (
            str_contains($message, 'clients_tenant_ico_unique')
            || (str_contains($message, 'clients') && str_contains($message, 'ico') && $e->getCode() === '23000')
        ) {
            throw ValidationException::withMessages([
                'ico' => [__('validation.unique', ['attribute' => 'ico'])],
            ]);
        }

        throw $e;
    }
}
