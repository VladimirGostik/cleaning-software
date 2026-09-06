<?php

declare(strict_types=1);

namespace App\Data\Objects;

use App\Models\CleaningObject;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ObjectOptionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $client_id,
        public readonly ?string $client_name,
        public readonly bool $is_active,
    ) {}

    public static function fromModel(CleaningObject $object): self
    {
        return new self(
            id: $object->id,
            name: $object->name,
            client_id: $object->client_id,
            client_name: $object->client?->name,
            is_active: (bool) $object->is_active,
        );
    }
}
