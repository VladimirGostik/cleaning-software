<?php

declare(strict_types=1);

namespace App\Services\Ai\Guard;

final readonly class InjectionVerdict
{
    /** @param  list<string>  $rules */
    public function __construct(
        public bool $flagged,
        public int $score,
        public array $rules,
        public ?string $evidence = null,
    ) {}

    public static function clean(): self
    {
        return new self(false, 0, []);
    }
}
