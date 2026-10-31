<?php

declare(strict_types=1);

namespace App\Data\Devices;

use App\Enums\DevicePlatformEnum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DeviceRegisterData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $token,
        #[Required]
        public readonly DevicePlatformEnum $platform,
        #[Nullable, Max(32)]
        public readonly ?string $app_version = null,
    ) {}
}
