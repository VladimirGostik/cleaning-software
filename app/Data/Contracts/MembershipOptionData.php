<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Models\TenantMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MembershipOptionData extends Data
{
    public function __construct(
        public string $id,
        public string $user_name,
        public string $user_email,
        public bool $is_active,
    ) {}

    public static function fromModel(TenantMembership $membership): self
    {
        return new self(
            id: $membership->id,
            user_name: $membership->user->name,
            user_email: $membership->user->email,
            is_active: $membership->is_active,
        );
    }
}
