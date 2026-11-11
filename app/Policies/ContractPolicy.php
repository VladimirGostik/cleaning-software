<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContractableTypeEnum;
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

    public function viewBody(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::ViewContracts->value) && $this->mayReadText($user, $contract);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateContracts->value);
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::EditContracts->value) && ($contract->isEditable() || $contract->isFile()) && $this->mayReadText($user, $contract);
    }

    public function sign(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::EditContracts->value) && $contract->canBeSigned() && $this->mayReadText($user, $contract);
    }

    public function terminate(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::TerminateContracts->value) && $contract->canBeTerminated();
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::DeleteContracts->value) && ($contract->isEditable() || $contract->isFile()) && $this->mayReadText($user, $contract);
    }

    public function downloadPdf(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::ViewContracts->value) && ! $contract->isFile() && $this->mayReadText($user, $contract);
    }

    public function manageFiles(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::EditContracts->value) && $this->mayReadText($user, $contract);
    }

    private function mayReadText(User $user, Contract $contract): bool
    {
        return $contract->contractable_type !== ContractableTypeEnum::TenantMembership->value
            || $user->can(PermissionEnum::ViewEmployeePersonalData->value);
    }
}
