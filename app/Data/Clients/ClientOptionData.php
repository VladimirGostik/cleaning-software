<?php

declare(strict_types=1);

namespace App\Data\Clients;

use App\Models\Client;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ClientOptionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromModel(Client $client): self
    {
        return new self(
            id: $client->id,
            name: $client->name,
        );
    }
}
