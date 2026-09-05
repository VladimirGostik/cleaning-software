<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MediaIndexFilterData extends Data
{
    public function __construct(
        #[MapInputName('filter.search')]
        public readonly ?string $search = null,
        #[MapInputName('filter.model_type')]
        public readonly ?string $model_type = null,
        #[MapInputName('filter.collection_name')]
        public readonly ?string $collection_name = null,
        #[MapInputName('filter.mime_type')]
        public readonly ?string $mime_type = null,
        public readonly ?string $sort = null,
        public readonly ?int $per_page = null,
    ) {}
}
