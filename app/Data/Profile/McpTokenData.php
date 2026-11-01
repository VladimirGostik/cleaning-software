<?php

declare(strict_types=1);

namespace App\Data\Profile;

use Laravel\Sanctum\PersonalAccessToken;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class McpTokenData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $created_at,
        public readonly ?string $last_used_at,
        public readonly ?string $expires_at,
    ) {}

    public static function fromToken(PersonalAccessToken $token): self
    {
        return new self(
            id: (string) $token->id,
            name: $token->name,
            created_at: $token->created_at?->toIso8601String() ?? '',
            last_used_at: $token->last_used_at?->toIso8601String(),
            expires_at: $token->expires_at?->toIso8601String(),
        );
    }
}
