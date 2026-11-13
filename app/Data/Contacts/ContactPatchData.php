<?php

declare(strict_types=1);

namespace App\Data\Contacts;

use Spatie\LaravelData\Attributes\Validation\Accepted;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Filled;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class ContactPatchData extends Data
{
    public function __construct(
        #[Filled, Max(255)]
        public readonly string|Optional $name = new Optional,
        #[Nullable, Max(255)]
        public readonly string|null|Optional $position = new Optional,
        #[Nullable, Email, Max(255)]
        public readonly string|null|Optional $email = new Optional,
        #[Nullable, Max(64)]
        public readonly string|null|Optional $phone = new Optional,
        #[Accepted]
        public readonly bool|Optional $is_primary = new Optional,
    ) {}
}
