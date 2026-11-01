<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AuthTokenData extends Data
{
    public function __construct(
        public readonly string $token,
        public readonly string $refresh_token,
        public readonly UserListItemData $user,
    ) {}

    public static function make(User $user, MobileTokenPairData $pair): self
    {
        return new self(
            token: $pair->token,
            refresh_token: $pair->refresh_token,
            user: UserListItemData::fromModel($user),
        );
    }

    /** Used by ResponseFromSpatieData Scribe strategy to generate example docs. */
    public static function fromModel(User $user): self
    {
        return new self(
            token: '1|example_sanctum_token_here',
            refresh_token: '0199e0f0-0000-7000-8000-000000000000.example_refresh_secret',
            user: UserListItemData::fromModel($user),
        );
    }
}
