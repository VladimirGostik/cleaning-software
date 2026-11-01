<?php

declare(strict_types=1);

namespace App\Services\Ai;

final readonly class TaskExtractionContext
{
    /**
     * @param  list<array{id: string, name: string}>  $companies
     * @param  list<array{id: string, name: string}>  $members
     */
    public function __construct(
        public string $today,
        public string $timezone,
        public array $companies,
        public ?string $companyId,
        public array $members,
    ) {}
}
