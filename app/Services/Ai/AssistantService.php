<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\Ai\ChatsWithTools;
use App\Data\Ai\AssistantReplyData;
use App\Data\Ai\AssistantRequestData;
use App\Exceptions\AiUnavailableException;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class AssistantService
{
    public function __construct(private ChatsWithTools $assistant) {}

    public function reply(AssistantRequestData $request, User $user): AssistantReplyData
    {
        try {
            return $this->assistant->reply($request, $user);
        } catch (AiUnavailableException) {
            throw ValidationException::withMessages(['ai' => [__('app.assistant_unavailable')]]);
        }
    }
}
