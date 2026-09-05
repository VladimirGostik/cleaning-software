<?php

declare(strict_types=1);

namespace App\Utils;

final class SymbolOperators
{
    /**
     * @return array{0: string, 1: mixed}
     */
    public static function parse(mixed $value): array
    {
        if (! is_string($value)) {
            return ['=', $value];
        }

        $operators = [
            'between:' => 'between',
            '!=:' => '!=',
            '<=:' => '<=',
            '>=:' => '>=',
            '~:' => '~',
            '<:' => '<',
            '>:' => '>',
            '=:' => '=',
        ];

        foreach ($operators as $prefix => $operator) {
            if (str_starts_with($value, $prefix)) {
                return [$operator, substr($value, strlen($prefix))];
            }
        }

        return ['=', $value];
    }

    public static function cleanValue(mixed $value): mixed
    {
        return self::parse($value)[1];
    }
}
