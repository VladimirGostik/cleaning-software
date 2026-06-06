<?php

declare(strict_types=1);

namespace App\Data\Clients;

use App\Enums\ClientTypeEnum;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ClientIndexFilterData extends Data
{
    public function __construct(
        #[Nullable, Max(255)]
        public ?string $search = null,
        #[Nullable]
        public ?ClientTypeEnum $type = null,
        #[In(['name', '-name', 'created_at', '-created_at'])]
        public string $sort = 'name',
        #[Min(1)]
        public int $page = 1,
        #[Between(10, 100)]
        public int $per_page = 20,
    ) {}
}
