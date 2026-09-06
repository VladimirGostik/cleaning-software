<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Contract;
use App\Models\User;

final class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewContracts->value);
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::ViewContracts->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateContracts->value);
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::EditContracts->value) && $contract->isEditable();
    }

    public function sign(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::EditContracts->value) && $contract->canBeSigned();
    }

    public function terminate(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::TerminateContracts->value) && $contract->canBeTerminated();
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::DeleteContracts->value) && $contract->isEditable();
    }

    public function downloadPdf(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::ViewContracts->value);
    }
}
