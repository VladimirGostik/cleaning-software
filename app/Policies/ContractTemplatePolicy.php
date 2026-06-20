<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\ContractTemplate;
use App\Models\User;

final class ContractTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewContractTemplates->value);
    }

    public function view(User $user, ContractTemplate $template): bool
    {
        return $user->can(PermissionEnum::ViewContractTemplates->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateContractTemplates->value);
    }

    public function update(User $user, ContractTemplate $template): bool
    {
        return $user->can(PermissionEnum::EditContractTemplates->value);
    }

    public function delete(User $user, ContractTemplate $template): bool
    {
        return $user->can(PermissionEnum::DeleteContractTemplates->value);
    }
}
