<?php

declare(strict_types=1);

namespace App\Data\Auth;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MeData extends Data
{
    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $features
     */
    public function __construct(
        public string $userId,
        public ?string $activeTenantId,
        public array $permissions,
        public array $features,
    ) {}
}
