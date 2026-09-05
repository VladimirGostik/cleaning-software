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
        public readonly UserListItemData $user,
    ) {}

    public static function make(User $user, string $token): self
    {
        return new self(
            token: $token,
            user: UserListItemData::fromModel($user),
        );
    }

    /** Used by ResponseFromSpatieData Scribe strategy to generate example docs. */
    public static function fromModel(User $user): self
    {
        return new self(
            token: '1|example_sanctum_token_here',
            user: UserListItemData::fromModel($user),
        );
    }
}
