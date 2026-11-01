<?php

declare(strict_types=1);

namespace App\Services\Documents;

final readonly class DocumentRenderContext
{
    /**
     * @param  array<string, string|null>  $values  plain text per token key; null = known token without a value
     * @param  array<string, bool>  $flags
     * @param  array<string, string>  $blocks  ready HTML per block key
     */
    public function __construct(
        public array $values = [],
        public array $flags = [],
        public array $blocks = [],
    ) {}
}
