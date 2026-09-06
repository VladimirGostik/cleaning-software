<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Enums\EmploymentContractTypeEnum;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmploymentContractUpsertData extends Data
{
    public function __construct(
        #[Required]
        public readonly EmploymentContractTypeEnum $employment_type,
        #[Nullable, Max(255)]
        public readonly ?string $position = null,
        #[Nullable, Min(0)]
        public readonly ?float $hourly_rate = null,
        #[Nullable, Min(0)]
        public readonly ?float $monthly_salary = null,
        #[Nullable, Min(0), Max(168)]
        public readonly ?float $weekly_hours = null,
        #[Nullable, Date]
        public readonly ?string $probation_end_date = null,
    ) {}
}
