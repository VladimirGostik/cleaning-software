<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;

final class ContactPolicy
{
    public function viewOn(User $user, Client|CleaningObject $owner): bool
    {
        return $user->can('view', $owner) && $this->mayHandleContacts($user, $owner);
    }

    public function manageOn(User $user, Client|CleaningObject $owner): bool
    {
        return $user->can('update', $owner) && $this->mayHandleContacts($user, $owner);
    }

    public function update(User $user, Contact $contact): bool
    {
        $owner = $contact->contactable;

        return ($owner instanceof Client || $owner instanceof CleaningObject) && $this->manageOn($user, $owner);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->update($user, $contact);
    }

    private function mayHandleContacts(User $user, Client|CleaningObject $owner): bool
    {
        return $owner instanceof Client || $user->can('viewContacts', CleaningObject::class);
    }
}
