<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PeppolParticipantId implements ValidationRule
{
    public const string PATTERN = '/^(\d{4}):([A-Za-z0-9\-\.]+)$/';

    public const string DIC_PATTERN = '/^\d{10}$/';

    public static function isValid(string $value): bool
    {
        if (preg_match(self::PATTERN, $value, $m) !== 1) {
            return false;
        }

        return $m[1] !== config('efa.dic_endpoint_scheme_id') || preg_match(self::DIC_PATTERN, $m[2]) === 1;
    }

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

            return;
        }

        if (! self::isValid($value)) {
            $fail(__('app.peppol_participant_dic_invalid'));
        }
    }
}
