<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class Filters
{
    public static function escapeLikeValue(string $value): string
    {
        return str_replace(
            ['\\', '_', '%'],
            ['\\\\', '\\_', '\\%'],
            $value,
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function whereContains(Builder $query, string $column, string $value, string $boolean = 'and'): void
    {
        $pattern = '%'.self::escapeLikeValue($value).'%';

        if (self::isPgsql($query)) {
            self::whereRawSql($query, 'f_unaccent('.self::wrap($query, $column).'::text) ilike f_unaccent(?)', [$pattern], $boolean);

            return;
        }

        $query->where($column, 'like', $pattern, $boolean);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function whereStartsWith(Builder $query, string $column, string $value): void
    {
        $pattern = self::escapeLikeValue($value).'%';

        if (self::isPgsql($query)) {
            self::whereRawSql($query, self::wrap($query, $column).'::text ilike ?', [$pattern]);

            return;
        }

        $query->where($column, 'like', $pattern);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function whereTextEquals(Builder $query, string $column, string $value, bool $negate = false): void
    {
        if (self::isPgsql($query)) {
            $operator = $negate ? '<>' : '=';

            self::whereRawSql(
                $query,
                'lower(f_unaccent('.self::wrap($query, $column).'::text)) '.$operator.' lower(f_unaccent(?))',
                [$value],
            );

            return;
        }

        $query->where($column, $negate ? '!=' : '=', $value);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function isPgsql(Builder $query): bool
    {
        return $query->getModel()->getConnection()->getDriverName() === 'pgsql';
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function wrap(Builder $query, string $column): string
    {
        return $query->getQuery()->getGrammar()->wrap($column);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $bindings
     */
    private static function whereRawSql(Builder $query, string $sql, array $bindings, string $boolean = 'and'): void
    {
        /** @var literal-string $sql */
        $query->whereRaw($sql, $bindings, $boolean);
    }
}
