<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

final class PendingActionSubjectChangedException extends ValidationException
{
    public static function make(): static
    {
        return self::withMessages(['status' => [__('app.pending_action_subject_changed')]]);
    }
}
