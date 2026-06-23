<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobCalendarFilterData extends Data
{
    public function __construct(
        public string $date_from,
        public string $date_to,
        public ?string $cleaning_object_id = null,
        public ?string $assigned_membership_id = null,
    ) {}
}
