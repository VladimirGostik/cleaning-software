<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PeppolParticipantId implements ValidationRule
{
    public const string PATTERN = '/^(\d{4}):([A-Za-z0-9\-\.]+)$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || preg_match(self::PATTERN, $value, $m) !== 1) {
            $fail(__('app.peppol_participant_id_invalid'));

            return;
        }

        $schemes = array_filter((array) config('efa.participant_schemes', []), is_string(...));

        if ($schemes !== [] && ! in_array($m[1], $schemes, true)) {
            $fail(__('app.peppol_participant_scheme_unknown', ['scheme' => $m[1]]));
        }
    }
}
