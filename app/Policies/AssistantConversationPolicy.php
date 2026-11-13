<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ActionChannelEnum;
use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Illuminate\Auth\Access\Response;

final readonly class AssistantConversationPolicy
{
    public function __construct(private TenantPermissionSnapshot $snapshot) {}

    public function viewAny(User $user): bool
    {
        return $this->snapshot->forChannel($user, ActionChannelEnum::Assistant) !== [];
    }

    public function view(User $user, AssistantConversation $conversation): Response
    {
        return $this->owned($user, $conversation);
    }

    public function update(User $user, AssistantConversation $conversation): Response
    {
        return $this->owned($user, $conversation);
    }

    public function delete(User $user, AssistantConversation $conversation): Response
    {
        return $conversation->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    private function owned(User $user, AssistantConversation $conversation): Response
    {
        if (! $this->viewAny($user)) {
            return Response::deny();
        }

        return $conversation->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
