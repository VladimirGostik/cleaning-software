<?php

declare(strict_types=1);

namespace App\Data\Employees;

use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeeIndexFilterData extends Data
{
    public function __construct(
        #[Nullable, Max(255)]
        public ?string $search = null,
        #[Nullable, Max(100)]
        public ?string $role = null,
        #[Nullable]
        public ?bool $is_active = null,
        #[In(['joined_at', '-joined_at'])]
        public string $sort = '-joined_at',
        #[Min(1)]
        public int $page = 1,
        #[Between(10, 100)]
        public int $per_page = 15,
    ) {}
}
