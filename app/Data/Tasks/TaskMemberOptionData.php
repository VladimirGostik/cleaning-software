<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Models\TenantMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskMemberOptionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}

    public static function fromMembership(TenantMembership $membership): self
    {
        return new self(id: $membership->id, name: $membership->display_name);
    }
}
