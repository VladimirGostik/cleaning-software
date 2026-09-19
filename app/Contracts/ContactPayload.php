<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Shared shape for a submitted contact row (`ClientContactData` / `ObjectContactData`) — lets
 * `ContactCollectionSynchronizer` operate on either without depending on a concrete DTO.
 */
interface ContactPayload
{
    public ?string $id { get; }

    public string $name { get; }

    public ?string $position { get; }

    public ?string $email { get; }

    public ?string $phone { get; }

    public bool $is_primary { get; }
}
