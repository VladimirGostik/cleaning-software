<?php

declare(strict_types=1);

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

final class WorkRecordPerformedAt implements ValidationRule
{
    private const string PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,9})?)?(Z|[+-]\d{2}:?\d{2})$/';

    public function __construct(private readonly ?int $backdateDays, private readonly bool $enforceWindow = true) {}

    public static function parse(string $value): ?CarbonImmutable
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            return null;
        }

        try {
            $at = CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }

        return $at->lt(CarbonImmutable::parse('2000-01-01T00:00:00Z')) ? null : $at;
    }

    public static function windowViolation(CarbonImmutable $at, ?int $backdateDays): ?string
    {
        if ($at->gt(now()->addMinutes(config()->integer('scheduling.work_records.future_tolerance_minutes')))) {
            return __('app.work_record_performed_at_future');
        }

        if ($backdateDays !== null && $at->lt(now()->subDays($backdateDays))) {
            return __('app.work_record_performed_at_too_old', ['days' => $backdateDays]);
        }

        return null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $at = is_string($value) ? self::parse($value) : null;

        if ($at === null) {
            $fail(__('app.work_record_performed_at_invalid'));

            return;
        }

        if ($this->enforceWindow && ($message = self::windowViolation($at, $this->backdateDays)) !== null) {
            $fail($message);
        }
    }
}
