<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum TaskReminderSlotEnum: string
{
    public const string TIMEZONE = 'Europe/Bratislava';

    case Morning = 'morning';
    case Noon = 'noon';
    case Evening = 'evening';

    public function hour(): int
    {
        return match ($this) {
            self::Morning => 7,
            self::Noon => 12,
            self::Evening => 19,
        };
    }

    /** The UTC instant of this slot on a local `Y-m-d` date. */
    public function instantOn(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::TIMEZONE)
            ->setTime($this->hour(), 0)
            ->utc();
    }

    /** First slot instant at or after the instant truncated to the minute, in UTC. */
    public static function nextAtOrAfter(CarbonInterface $instant): CarbonImmutable
    {
        return self::resolve($instant)[0]->utc();
    }

    /** @return array{string, self} Local `Y-m-d` and slot of the first slot at or after the instant. */
    public static function dateAndSlotFor(CarbonInterface $instant): array
    {
        [$local, $slot] = self::resolve($instant);

        return [$local->format('Y-m-d'), $slot];
    }

    /** @return array{string, self} */
    public static function firstAfter(CarbonInterface $instant): array
    {
        return self::dateAndSlotFor(CarbonImmutable::instance($instant)->addMinute());
    }

    /** @return array{CarbonImmutable, self} Slot instant in local time. */
    private static function resolve(CarbonInterface $instant): array
    {
        $local = CarbonImmutable::instance($instant)->setTimezone(self::TIMEZONE)->startOfMinute();

        foreach ([0, 1] as $days) {
            foreach (self::cases() as $slot) {
                $candidate = $local->setTime(0, 0)->addDays($days)->setTime($slot->hour(), 0);

                if ($candidate->greaterThanOrEqualTo($local)) {
                    return [$candidate, $slot];
                }
            }
        }

        return [$local, self::Morning];
    }
}
