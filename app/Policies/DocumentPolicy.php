<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Document;
use App\Models\TenantMembership;
use App\Models\User;

final class DocumentPolicy
{
    public function attach(User $user, TenantMembership $employee): bool
    {
        return $this->canManage($user) && $employee->tenant_id === current_tenant_id();
    }

    public function download(User $user, Document $document): bool
    {
        return $user->can(PermissionEnum::ViewEmployeeDocuments->value)
            && $user->can(PermissionEnum::ViewEmployees->value)
            && $this->isEmployeeFile($document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->canManage($user) && $this->isEmployeeFile($document);
    }

    private function canManage(User $user): bool
    {
        return $user->can(PermissionEnum::ManageEmployeeDocuments->value)
            && $user->can(PermissionEnum::ViewEmployees->value);
    }

    private function isEmployeeFile(Document $document): bool
    {
        return $document->type->isEmployeeFile() && $document->tenant_id === current_tenant_id();
    }
}
