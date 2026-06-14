<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Invoice;
use App\Models\User;

final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewInvoices->value);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can(PermissionEnum::ViewInvoices->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateInvoices->value);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can(PermissionEnum::EditInvoices->value);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $user->can(PermissionEnum::EditInvoices->value);
    }

    public function markPaid(User $user, Invoice $invoice): bool
    {
        return $user->can(PermissionEnum::EditInvoices->value);
    }

    public function bulkMarkPaid(User $user): bool
    {
        return $user->can(PermissionEnum::EditInvoices->value);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->can(PermissionEnum::CancelInvoices->value);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can(PermissionEnum::CancelInvoices->value);
    }

    public function duplicate(User $user, Invoice $invoice): bool
    {
        return $user->can(PermissionEnum::CreateInvoices->value);
    }
}
