<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobIndexFilterData extends Data
{
    public function __construct(
        #[MapInputName('filter.search')]
        public ?string $search = null,
        #[MapInputName('filter.status')]
        public ?JobStatusEnum $status = null,
        #[MapInputName('filter.type')]
        public ?JobTypeEnum $type = null,
        #[MapInputName('filter.cleaning_object_id')]
        public ?string $cleaning_object_id = null,
        #[MapInputName('filter.assigned_membership_id')]
        public ?string $assigned_membership_id = null,
        #[MapInputName('filter.date_from')]
        public ?string $date_from = null,
        #[MapInputName('filter.date_to')]
        public ?string $date_to = null,
        public int $per_page = 25,
    ) {}
}
