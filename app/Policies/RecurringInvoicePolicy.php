<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\RecurringInvoice;
use App\Models\User;

final class RecurringInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewRecurringInvoices->value);
    }

    public function view(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $user->can(PermissionEnum::ViewRecurringInvoices->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateRecurringInvoices->value);
    }

    public function update(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $user->can(PermissionEnum::EditRecurringInvoices->value);
    }

    public function pause(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $user->can(PermissionEnum::EditRecurringInvoices->value);
    }

    public function resume(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $user->can(PermissionEnum::EditRecurringInvoices->value);
    }

    public function cancel(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $user->can(PermissionEnum::DeleteRecurringInvoices->value);
    }

    public function delete(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $user->can(PermissionEnum::DeleteRecurringInvoices->value);
    }
}
