<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\MobileSession;
use Laravel\Sanctum\Events\TokenAuthenticated;

final class ConfirmMobileSessionRotation
{
    /** Sanctum fires this before it writes `last_used_at`, so a null value means the first use of this access token. */
    public function handle(TokenAuthenticated $event): void
    {
        if ($event->token->last_used_at !== null) {
            return;
        }

        MobileSession::query()
            ->where('personal_access_token_id', $event->token->getKey())
            ->whereNull('rotation_confirmed_at')
            ->update(['rotation_confirmed_at' => now()]);
    }
}
