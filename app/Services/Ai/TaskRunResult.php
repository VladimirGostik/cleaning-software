<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Data\Ai\AssistantActionData;
use App\Enums\TaskRunOutcomeEnum;

final readonly class TaskRunResult
{
    /**
     * @param  list<AssistantActionData>  $actions
     */
    public function __construct(
        public ?TaskRunOutcomeEnum $outcome = null,
        public string $message = '',
        public array $actions = [],
        public bool $restricted = false,
        public int $refusedWrites = 0,
        public bool $providerFailed = false,
        public bool $cutShort = false,
    ) {}

    public static function providerFailed(): self
    {
        return new self(providerFailed: true);
    }
}
