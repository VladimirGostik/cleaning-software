<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

abstract class ListTool extends TenantTool
{
    private const int MAX_PER_PAGE = 50;

    /** @return list<string> Argument names forwarded as `filter[<name>]`. */
    abstract protected function filterKeys(): array;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $rules = [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'sort' => ['sometimes', 'string', 'max:40', 'regex:/^-?[a-z_]+$/'],
        ];

        foreach ($this->filterKeys() as $key) {
            $rules[$key] = str_ends_with($key, '_id') ? ['sometimes', 'uuid'] : ['sometimes', 'string', 'max:100'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function requestInput(array $arguments): array
    {
        $filters = [];

        foreach ($this->filterKeys() as $key) {
            if (isset($arguments[$key]) && is_scalar($arguments[$key])) {
                $filters[$key] = (string) $arguments[$key];
            }
        }

        $perPage = isset($arguments['per_page']) && is_numeric($arguments['per_page']) ? (int) $arguments['per_page'] : 25;

        return array_filter([
            'filter' => $filters === [] ? null : $filters,
            'sort' => isset($arguments['sort']) && is_string($arguments['sort']) ? $arguments['sort'] : null,
            'page' => isset($arguments['page']) && is_numeric($arguments['page']) ? (int) $arguments['page'] : null,
            'per_page' => max(1, min(self::MAX_PER_PAGE, $perPage)),
        ], fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, Type> */
    protected function pagingProperties(JsonSchema $schema): array
    {
        return [
            'per_page' => $schema->integer()->min(1)->max(self::MAX_PER_PAGE)->description('Rows per page, default 25, at most 50.'),
            'page' => $schema->integer()->min(1)->description('Page number, starting at 1.'),
            'sort' => $schema->string()->description('Sort column; prefix with - for descending.'),
        ];
    }
}
