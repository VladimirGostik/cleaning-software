<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Enums\AssistantRoleEnum;
use Spatie\LaravelData\Data;

final class AssistantMessageData extends Data
{
    /** @param  list<string>  $attachment_names */
    public function __construct(
        public readonly AssistantRoleEnum $role,
        public readonly string $content = '',
        public readonly array $attachment_names = [],
    ) {}
}
