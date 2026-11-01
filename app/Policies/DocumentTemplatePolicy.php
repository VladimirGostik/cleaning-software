<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocumentTemplateStateEnum;
use App\Enums\PermissionEnum;
use App\Models\DocumentTemplate;
use App\Models\User;

final class DocumentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewDocumentTemplates->value);
    }

    public function view(User $user, DocumentTemplate $template): bool
    {
        return $user->can(PermissionEnum::ViewDocumentTemplates->value);
    }

    public function preview(User $user): bool
    {
        return $user->can(PermissionEnum::ViewDocumentTemplates->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateDocumentTemplates->value);
    }

    public function createGlobal(User $user): bool
    {
        return $user->is_platform_admin;
    }

    public function update(User $user, DocumentTemplate $template): bool
    {
        if ($template->tenant_id === null) {
            return $user->is_platform_admin;
        }

        return $user->can(PermissionEnum::EditDocumentTemplates->value);
    }

    public function delete(User $user, DocumentTemplate $template): bool
    {
        if ($template->tenant_id === null) {
            return $user->is_platform_admin;
        }

        return $template->parent_id === null && $user->can(PermissionEnum::DeleteDocumentTemplates->value);
    }

    public function customize(User $user, DocumentTemplate $template): bool
    {
        return $template->tenant_id === null
            && $template->is_active
            && $user->can(PermissionEnum::CreateDocumentTemplates->value)
            && ! $template->overrides()->where('tenant_id', current_tenant_id())->exists();
    }

    public function reset(User $user, DocumentTemplate $template): bool
    {
        return $template->parent_id !== null && $user->can(PermissionEnum::EditDocumentTemplates->value);
    }

    public function acknowledge(User $user, DocumentTemplate $template): bool
    {
        return $template->parent_id !== null
            && $template->state() === DocumentTemplateStateEnum::BaseChanged
            && $user->can(PermissionEnum::EditDocumentTemplates->value);
    }
}
