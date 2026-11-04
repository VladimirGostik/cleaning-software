<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class PasswordChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
