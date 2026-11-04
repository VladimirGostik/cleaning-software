<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contacts\ContactData;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

final readonly class ContactCollectionSynchronizer
{
    /**
     * @param  MorphMany<Contact, covariant Model>  $relation
     * @param  array<int, ContactData>  $contacts
     */
    public function sync(MorphMany $relation, array $contacts, string $invalidMessageKey): void
    {
        $existingById = $relation->get()->keyBy('id');
        $incomingIds = array_values(array_filter(array_map(fn (ContactData $contact) => $contact->id, $contacts)));

        foreach ($incomingIds as $incomingId) {
            if (! $existingById->has($incomingId)) {
                throw ValidationException::withMessages([
                    'contacts' => [__($invalidMessageKey)],
                ]);
            }
        }

        foreach ($existingById as $id => $existing) {
            if (! in_array($id, $incomingIds, true)) {
                $existing->delete();
            }
        }

        $hasPrimary = array_any($contacts, fn (ContactData $contact) => $contact->is_primary);

        foreach ($contacts as $index => $contact) {
            $attributes = [
                'name' => $contact->name,
                'position' => $contact->position,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'is_primary' => $contact->is_primary || (! $hasPrimary && $index === 0),
            ];

            if ($contact->id !== null) {
                $existingById->get($contact->id)?->update($attributes);
            } else {
                $relation->create($attributes);
            }
        }
    }
}
