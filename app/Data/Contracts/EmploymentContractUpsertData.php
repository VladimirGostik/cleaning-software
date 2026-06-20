<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Enums\EmploymentContractTypeEnum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmploymentContractUpsertData extends Data
{
    public function __construct(
        #[Required]
        public EmploymentContractTypeEnum $employment_type,
        #[Nullable, Max(255)]
        public ?string $position = null,
        #[Nullable]
        public ?float $hourly_rate = null,
        #[Nullable]
        public ?float $monthly_salary = null,
        #[Nullable]
        public ?float $weekly_hours = null,
        #[Nullable]
        public ?string $probation_end_date = null,
    ) {}
}
