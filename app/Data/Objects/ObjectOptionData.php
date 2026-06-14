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
        public string $id,
        public string $name,
        public string $client_id,
    ) {}

    public static function fromModel(CleaningObject $object): self
    {
        return new self(
            id: $object->id,
            name: $object->name,
            client_id: $object->client_id,
        );
    }
}
