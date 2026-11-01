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
        public readonly string $id,
        public readonly string $label,
        public readonly bool $is_active,
    ) {}

    public static function fromModel(TenantMembership $membership): self
    {
        $email = $membership->display_email;

        return new self(
            id: $membership->id,
            label: $email === null || $email === '' ? $membership->display_name : $membership->display_name.' ('.$email.')',
            is_active: $membership->is_active,
        );
    }
}
