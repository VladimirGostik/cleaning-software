<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Arr;

final readonly class McpResult
{
    /** @param  array<string, mixed>  $data */
    public function __construct(public bool $isError, public string $text, public array $data) {}

    public function get(string $path): mixed
    {
        return Arr::get($this->data, $path);
    }

    public function string(string $path): string
    {
        $value = $this->get($path);

        return is_scalar($value) ? (string) $value : '';
    }

    public function int(string $path): int
    {
        $value = $this->get($path);

        return is_numeric($value) ? (int) $value : -1;
    }

    public function bool(string $path): bool
    {
        return $this->get($path) === true;
    }

    /** @return list<array<string, mixed>> */
    public function rows(string $path = 'items'): array
    {
        $rows = $this->get($path);

        /** @var list<array<string, mixed>> */
        return is_array($rows) ? array_values($rows) : [];
    }

    /** @return list<mixed> */
    public function column(string $path, string $key): array
    {
        return array_column($this->rows($path), $key);
    }

    /** @return list<mixed> */
    public function ids(string $path = 'items'): array
    {
        return $this->column($path, 'id');
    }
}
