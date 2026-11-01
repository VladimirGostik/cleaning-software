<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Data\Ai\AssistantReplyData;
use App\Data\Ai\AssistantRequestData;
use App\Exceptions\AiUnavailableException;
use App\Models\User;

interface ChatsWithTools
{
    /** @throws AiUnavailableException */
    public function reply(AssistantRequestData $request, User $user): AssistantReplyData;
}
