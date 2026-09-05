<?php

declare(strict_types=1);

namespace App\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final class ResponseFromSpatieData
{
    /**
     * @param  class-string  $dataClass  Spatie Data DTO with a static fromModel() factory
     * @param  class-string  $model  Eloquent model class to instantiate via factory
     * @param  string[]  $states  Factory states to apply
     * @param  string[]  $with  Relations to eager-load before calling fromModel()
     * @param  bool  $paginated  Wrap result in a standard Laravel paginator envelope
     */
    public function __construct(
        public readonly string $dataClass,
        public readonly string $model,
        public readonly int $status = 200,
        public readonly string $description = '',
        public readonly array $states = [],
        public readonly array $with = [],
        public readonly bool $paginated = false,
    ) {}
}
