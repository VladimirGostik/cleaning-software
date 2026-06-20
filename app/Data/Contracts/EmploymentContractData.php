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
        public EmploymentContractTypeEnum $employment_type,
        public ?string $position = null,
        public ?string $hourly_rate = null,
        public ?string $monthly_salary = null,
        public ?string $weekly_hours = null,
        public ?string $probation_end_date = null,
    ) {}

    public static function fromModel(EmploymentContract $employment): self
    {
        return new self(
            employment_type: $employment->employment_type,
            position: $employment->position,
            hourly_rate: $employment->hourly_rate !== null ? (string) $employment->hourly_rate : null,
            monthly_salary: $employment->monthly_salary !== null ? (string) $employment->monthly_salary : null,
            weekly_hours: $employment->weekly_hours !== null ? (string) $employment->weekly_hours : null,
            probation_end_date: $employment->probation_end_date?->toDateString(),
        );
    }
}
