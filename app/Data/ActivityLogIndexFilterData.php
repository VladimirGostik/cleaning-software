<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ActivityLogIndexFilterData extends Data
{
    public function __construct(
        #[MapInputName('filter.search')]
        public readonly ?string $search = null,
        #[MapInputName('filter.subject_type')]
        public readonly ?string $subject_type = null,
        #[MapInputName('filter.user_filter')]
        public readonly ?string $user_filter = null,
        #[MapInputName('filter.date_from')]
        public readonly ?string $date_from = null,
        #[MapInputName('filter.date_to')]
        public readonly ?string $date_to = null,
        public readonly ?string $sort = null,
        public readonly ?int $perPage = null,
    ) {}
}
