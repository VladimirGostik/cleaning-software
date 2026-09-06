<?php

declare(strict_types=1);

namespace App\Utils;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\Filters\Filter;
use Spatie\QueryBuilder\Filters\FiltersOperator;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @method static static exact(string $name, ?string $internalName = null, bool $addRelationConstraint = true, ?string $arrayValueDelimiter = null): static
 * @method static static partial(string $name, ?string $internalName = null, bool $addRelationConstraint = true, ?string $arrayValueDelimiter = null): static
 * @method static static beginsWithStrict(string $name, ?string $internalName = null, bool $addRelationConstraint = true, ?string $arrayValueDelimiter = null): static
 * @method static static endsWithStrict(string $name, ?string $internalName = null, bool $addRelationConstraint = true, ?string $arrayValueDelimiter = null): static
 * @method static static scope(string $name, $internalName = null, ?string $arrayValueDelimiter = null): static
 * @method static static custom(string $name, Filter $filterClass, $internalName = null, ?string $arrayValueDelimiter = null): static
 * @method static static callback(string $name, $callback, $internalName = null, ?string $arrayValueDelimiter = null): static
 */
final class AllowedFilter extends \Spatie\QueryBuilder\AllowedFilter
{
    protected ?array $validationRules = null;

    public static function search(
        array $columns,
        string $name = 'search',
        ?string $internalName = null,
        ?string $arrayValueDelimiter = null,
    ): static {
        $likeOperator = self::defaultLikeOperator();

        return new self(
            $name,
            new class($columns, $likeOperator) implements Filter
            {
                /**
                 * @param  array<int, string>  $columns
                 */
                public function __construct(
                    private readonly array $columns,
                    private readonly string $likeOperator,
                ) {}

                public function __invoke(Builder $query, mixed $value, string $property): void
                {
                    $value = SymbolOperators::cleanValue($value);

                    if (blank($value)) {
                        return;
                    }

                    $query->where(function (Builder $query) use ($value): void {
                        foreach ($this->columns as $index => $column) {
                            $method = $index === 0 ? 'where' : 'orWhere';

                            $query->{$method}(
                                $column,
                                $this->likeOperator,
                                '%'.Filters::escapeLikeValue((string) $value).'%',
                            );
                        }
                    });
                }
            },
            $internalName,
        );
    }

    public static function contains(
        string $name,
        ?string $internalName = null,
        ?string $arrayValueDelimiter = null,
    ): static {
        $column = $internalName ?? $name;
        $likeOperator = static::defaultLikeOperator();

        return new static(
            $name,
            new class($column, $likeOperator) implements Filter
            {
                public function __construct(
                    private readonly string $column,
                    private readonly string $likeOperator,
                ) {}

                public function __invoke(Builder $query, mixed $value, string $property): void
                {
                    $value = SymbolOperators::cleanValue($value);

                    if (blank($value)) {
                        return;
                    }

                    $query->where(
                        $this->column,
                        $this->likeOperator,
                        '%'.Filters::escapeLikeValue((string) $value).'%',
                    );
                }
            },
            $internalName,
        );
    }

    public static function dynamic(
        string $name,
        ?string $internalName = null,
        ?string $arrayValueDelimiter = null,
    ): static {
        return new static(
            $name,
            new SymbolOperatorFilter(
                column: $internalName ?? $name,
                likeOperator: static::defaultLikeOperator(),
            ),
            $internalName,
        );
    }

    public static function relationExact(
        string $name,
        string $relation,
        string $column = 'id',
        ?string $arrayValueDelimiter = null,
    ): static {
        $likeOperator = static::defaultLikeOperator();

        return new static(
            $name,
            new class($relation, $column, $likeOperator) implements Filter
            {
                public function __construct(
                    private readonly string $relation,
                    private readonly string $column,
                    private readonly string $likeOperator,
                ) {}

                public function __invoke(Builder $query, mixed $value, string $property): void
                {
                    [$operator, $value] = SymbolOperators::parse($value);

                    if ($value === null || $value === '') {
                        return;
                    }

                    if ($operator === '~') {
                        $query->whereHas($this->relation, function (Builder $query) use ($value): void {
                            $query->where(
                                $this->column,
                                $this->likeOperator,
                                '%'.Filters::escapeLikeValue((string) $value).'%',
                            );
                        });

                        return;
                    }

                    if ($operator === '!=') {
                        $query->whereDoesntHave($this->relation, function (Builder $query) use ($value): void {
                            $query->where($this->column, '=', $value);
                        });

                        return;
                    }

                    $query->whereHas($this->relation, function (Builder $query) use ($operator, $value): void {
                        $query->where($this->column, $operator, $value);
                    });
                }
            },
        );
    }

    public static function callbackClean(
        string $name,
        Closure $callback,
        $internalName = null,
        ?string $arrayValueDelimiter = null,
    ): static {
        return new static(
            $name,
            new class($callback) implements Filter
            {
                public function __construct(
                    private readonly Closure $callback,
                ) {}

                public function __invoke(Builder $query, mixed $value, string $property): void
                {
                    $value = SymbolOperators::cleanValue($value);

                    call_user_func($this->callback, $query, $value, $property);
                }
            },
            $internalName,
        );
    }

    protected function splitFilterValue(mixed $value): mixed
    {
        if (is_string($value) && str_starts_with($value, 'between:')) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->splitFilterValue($item), $value);
        }

        return parent::splitFilterValue($value);
    }

    public function filter(QueryBuilder $query, $value): void
    {
        if (isset($this->validationRules)) {
            foreach (Arr::wrap($value) as $item) {
                $filterClass = $this->getFilterClass();
                $valueWithoutOperator = $item;

                if (
                    $filterClass instanceof FiltersOperator
                    || $filterClass instanceof SymbolOperatorFilter
                ) {
                    $valueWithoutOperator = SymbolOperators::cleanValue($item);
                }

                if (is_string($item) && str_starts_with($item, 'between:')) {
                    $betweenValue = substr($item, strlen('between:'));

                    $values = array_values(array_filter(
                        array_map('trim', explode(',', $betweenValue)),
                        static fn (string $value): bool => $value !== '',
                    ));

                    if (count($values) !== 2) {
                        Log::warning('Filter failed validation', [
                            'filter' => $this->name,
                            'value' => $value,
                            'valueWithoutOperator' => $valueWithoutOperator,
                            'rules' => $this->validationRules,
                            'reason' => 'between expects exactly two values',
                        ]);

                        return;
                    }

                    foreach ($values as $betweenItem) {
                        $validator = Validator::make(
                            ['value' => $betweenItem],
                            ['value' => $this->validationRules],
                        );

                        if ($validator->fails()) {
                            Log::warning('Filter failed validation', [
                                'filter' => $this->name,
                                'value' => $value,
                                'valueWithoutOperator' => $betweenItem,
                                'rules' => $this->validationRules,
                            ]);

                            return;
                        }
                    }

                    continue;
                }

                $validator = Validator::make(
                    ['value' => $valueWithoutOperator],
                    ['value' => $this->validationRules],
                );

                if ($validator->fails()) {
                    Log::warning('Filter failed validation', [
                        'filter' => $this->name,
                        'value' => $value,
                        'valueWithoutOperator' => $valueWithoutOperator,
                        'rules' => $this->validationRules,
                    ]);

                    return;
                }
            }
        }

        parent::filter($query, $value);
    }

    public function uuid(): static
    {
        $this->validationRules = ['uuid'];

        return $this;
    }

    public function date(): static
    {
        $this->validationRules = ['date_format:Y-m-d'];

        return $this;
    }

    public function isoDateTime(): static
    {
        $this->validationRules = ['date_format:'.DATE_ATOM];

        return $this;
    }

    public function integer(): static
    {
        $this->validationRules = ['integer'];

        return $this;
    }

    public function numeric(): static
    {
        $this->validationRules = ['numeric'];

        return $this;
    }

    public function boolean(): static
    {
        $this->validationRules = ['boolean'];

        return $this;
    }

    private static function defaultLikeOperator(): string
    {
        return config('database.default') === 'pgsql' ? 'ilike' : 'like';
    }
}
