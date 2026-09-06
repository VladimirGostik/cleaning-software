<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Quote;
use App\Models\User;

final class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewQuotes->value);
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->can(PermissionEnum::ViewQuotes->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateQuotes->value);
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->can(PermissionEnum::EditQuotes->value);
    }

    public function attachClient(User $user, Quote $quote): bool
    {
        return $user->can(PermissionEnum::EditQuotes->value);
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->can(PermissionEnum::DeleteQuotes->value);
    }

    public function send(User $user, Quote $quote): bool
    {
        return $user->can(PermissionEnum::SendQuotes->value);
    }

    public function accept(User $user, Quote $quote): bool
    {
        return $user->can(PermissionEnum::ApproveQuotes->value);
    }

    public function reject(User $user, Quote $quote): bool
    {
        return $user->can(PermissionEnum::ApproveQuotes->value);
    }

    public function duplicate(User $user, Quote $quote): bool
    {
        return $user->can(PermissionEnum::CreateQuotes->value);
    }

    public function convertToInvoice(User $user, Quote $quote): bool
    {
        return $user->can(PermissionEnum::CreateInvoices->value);
    }

    public function downloadPdf(User $user, Quote $quote): bool
    {
        return $user->can(PermissionEnum::ViewQuotes->value);
    }
}
