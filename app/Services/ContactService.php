<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contacts\ContactData;
use App\Data\Contacts\ContactPatchData;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contact;
use App\Support\ActivityBatch;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

final readonly class ContactService
{
    public function __construct(private DatabaseManager $db) {}

    public function create(Client|CleaningObject $owner, ContactData $data): Contact
    {
        return ActivityBatch::run(fn (): Contact => $this->db->transaction(function () use ($owner, $data): Contact {
            $existing = $this->lockContacts($owner);
            $primary = $data->is_primary || $existing->isEmpty();

            if ($primary) {
                $this->demote($existing);
            }

            return $owner->contacts()->create([
                'name' => $data->name,
                'position' => $data->position,
                'email' => $data->email,
                'phone' => $data->phone,
                'is_primary' => $primary,
            ]);
        }));
    }

    public function update(Contact $contact, ContactPatchData $data): Contact
    {
        return ActivityBatch::run(fn (): Contact => $this->db->transaction(function () use ($contact, $data): Contact {
            $others = $this->lockContacts($this->ownerOf($contact))->where('id', '!=', $contact->id);
            $contact->refresh();

            if ($data->is_primary === true) {
                $this->demote($others);
            }

            /** @var array<string, mixed> $attributes */
            $attributes = $data->toArray();
            $contact->update($attributes);

            return $contact;
        }));
    }

    public function delete(Contact $contact): void
    {
        ActivityBatch::run(fn () => $this->db->transaction(function () use ($contact): void {
            $owner = $this->ownerOf($contact);
            $this->lockContacts($owner);
            $contact->refresh();
            $wasPrimary = $contact->is_primary;

            $contact->delete();

            if (! $wasPrimary) {
                return;
            }

            $owner->contacts()->getQuery()->reorder()->orderBy('name')->orderBy('id')->first()?->update(['is_primary' => true]);
        }));
    }

    /** @return Collection<int, Contact> */
    private function lockContacts(Client|CleaningObject $owner): Collection
    {
        $owner::query()->whereKey($owner->getKey())->lockForUpdate()->first();

        return $owner->contacts()->getQuery()->reorder()->orderBy('id')->lockForUpdate()->get();
    }

    /** @param  Collection<int, Contact>  $contacts */
    private function demote(Collection $contacts): void
    {
        $contacts->where('is_primary', true)->each(fn (Contact $contact): bool => $contact->update(['is_primary' => false]));
    }

    private function ownerOf(Contact $contact): Client|CleaningObject
    {
        $owner = $contact->contactable;

        return $owner instanceof Client || $owner instanceof CleaningObject ? $owner : throw new LogicException('Contact has no client or object owner.');
    }
}
