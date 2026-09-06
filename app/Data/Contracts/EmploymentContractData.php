<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Enums\EmploymentContractTypeEnum;
use App\Models\EmploymentContract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmploymentContractData extends Data
{
    public function __construct(
        public readonly EmploymentContractTypeEnum $employment_type,
        public readonly ?string $position,
        public readonly ?string $hourly_rate,
        public readonly ?string $monthly_salary,
        public readonly ?string $weekly_hours,
        public readonly ?string $probation_end_date,
    ) {}

    public static function fromModel(EmploymentContract $employmentContract): self
    {
        return new self(
            employment_type: $employmentContract->employment_type,
            position: $employmentContract->position,
            hourly_rate: $employmentContract->hourly_rate,
            monthly_salary: $employmentContract->monthly_salary,
            weekly_hours: $employmentContract->weekly_hours,
            probation_end_date: $employmentContract->probation_end_date?->toDateString(),
        );
    }
}
