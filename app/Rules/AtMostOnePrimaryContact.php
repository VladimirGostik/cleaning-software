<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * At most one contact row in the submitted `contacts` array may have `is_primary = true`.
 * Shared by `ClientUpsertData` and `ObjectUpsertData` — message key differs per entity.
 */
final class AtMostOnePrimaryContact implements ValidationRule
{
    public function __construct(
        private readonly string $messageKey,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $primaryCount = collect($value)
            ->filter(fn (mixed $contact): bool => is_array($contact) && (bool) ($contact['is_primary'] ?? false))
            ->count();

        if ($primaryCount > 1) {
            $fail(__($this->messageKey));
        }
    }
}
