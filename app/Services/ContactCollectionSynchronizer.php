<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ContactPayload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Shared contact-list sync (used by both `Client::contacts()` and `CleaningObject::contacts()`):
 * rejects a contact id foreign to the relation, soft-deletes rows removed from the payload,
 * auto-promotes the first row to primary when none is marked, and upserts the rest.
 *
 * `$contacts` is a plain array (not Spatie's `DataCollection` / `Illuminate\Support\Collection`
 * directly) — both wrap their item template invariantly under Larastan's stubs, so a
 * `DataCollection<int, ClientContactData>` can never satisfy a `DataCollection<int,
 * ContactPayload>` parameter even though `ClientContactData implements ContactPayload`. Plain
 * PHP arrays stay covariant on their value type, so callers pass `$data->contacts->items()`.
 */
final readonly class ContactCollectionSynchronizer
{
    /**
     * @param  HasMany<covariant Model, covariant Model>  $relation
     * @param  array<int, ContactPayload>  $contacts
     */
    public function sync(HasMany $relation, array $contacts, string $invalidMessageKey): void
    {
        /** @var Collection<int, ContactPayload> $contactItems */
        $contactItems = collect($contacts);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Model> $existingContacts */
        $existingContacts = $relation->get();
        $existingById = $existingContacts->keyBy(function (Model $contact, int $key): string {
            /** @var string $id */
            $id = $contact->getKey();

            return $id;
        });

        /** @var list<string> $incomingIds */
        $incomingIds = $contactItems
            ->filter(fn (ContactPayload $c) => $c->id !== null)
            ->map(fn (ContactPayload $c) => $c->id)
            ->all();

        foreach ($incomingIds as $incomingId) {
            if (! $existingById->has($incomingId)) {
                throw ValidationException::withMessages([
                    'contacts' => [__($invalidMessageKey)],
                ]);
            }
        }

        foreach ($existingContacts as $existing) {
            if (! in_array($existing->getKey(), $incomingIds, true)) {
                $existing->delete();
            }
        }

        // Every remaining/new contact gets its `is_primary` set explicitly below, so no
        // separate "reset all to false" pass is needed first.
        $hasPrimary = $contactItems->contains(fn (ContactPayload $c) => $c->is_primary);

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

            $relation->create([
                'name' => $contactData->name,
                'position' => $contactData->position,
                'email' => $contactData->email,
                'phone' => $contactData->phone,
                'is_primary' => $isPrimary,
            ]);
        }
    }
}
