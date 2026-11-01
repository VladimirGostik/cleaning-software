<?php

declare(strict_types=1);

namespace App\Services\Expenses;

final readonly class ParsedStatement
{
    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $records
     */
    public function __construct(
        public string $delimiter,
        public array $header,
        public array $records,
    ) {}
}
