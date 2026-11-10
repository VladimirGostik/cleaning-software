<?php

declare(strict_types=1);

namespace App\Services\Expenses;

use App\Data\Expenses\ExpenseTotalData;
use App\Enums\CurrencyEnum;
use App\Models\Expense;
use App\Models\User;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ExpenseListQuery
{
    public function __construct(private TenantPermissionSnapshot $snapshot) {}

    /**
     * @param  Builder<Expense>  $base
     * @return list<ExpenseTotalData>
     */
    public function totals(Builder $base, Request $request): array
    {
        return array_values($this->filtered($base, $request)
            ->toBase()
            ->reorder()
            ->selectRaw('expenses.currency, sum(expenses.amount) as total, count(*) as aggregate_count')
            ->groupBy('expenses.currency')
            ->orderBy('expenses.currency')
            ->get()
            ->map(fn (object $row): ExpenseTotalData => new ExpenseTotalData(
                currency: CurrencyEnum::from(is_string($row->currency) ? $row->currency : ''),
                amount: number_format(is_numeric($row->total) ? (float) $row->total : 0.0, 2, '.', ''),
                count: is_numeric($row->aggregate_count) ? (int) $row->aggregate_count : 0,
            ))
            ->all());
    }

    /**
     * @param  Builder<Expense>  $base
     * @return QueryBuilder<Expense>
     */
    public function filtered(Builder $base, Request $request): QueryBuilder
    {
        $filters = $request->input('filter');
        $explicitUndocumented = is_array($filters) && array_key_exists('undocumented', $filters);

        if (! $explicitUndocumented) {
            $base->where('expenses.is_undocumented', false);
        }

        $actor = $request->user();
        $personalTenantIds = $actor instanceof User
            ? TenantVisibility::idsWhere($this->snapshot->forRequest($actor), static fn (TenantVisibility $visibility): bool => $visibility->readsEmployeePersonalData())
            : [];

        $base->where(fn (Builder $query) => $query
            ->whereNull('expenses.tenant_membership_id')
            ->orWhereIn('expenses.tenant_id', $personalTenantIds));

        return QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::callbackClean('search', function (Builder $query, mixed $value): void {
                    if (blank($value) || ! is_scalar($value)) {
                        return;
                    }

                    $like = '%'.Filters::escapeLikeValue((string) $value).'%';
                    $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

                    $query->where(function (Builder $q) use ($like, $operator): void {
                        $q->where('expenses.supplier_name', $operator, $like)->orWhere('expenses.description', $operator, $like);
                    });
                }),
                AllowedFilter::dynamic('status'),
                AllowedFilter::dynamic('category'),
                AllowedFilter::dynamic('source'),
                AllowedFilter::dynamic('tenant_id')->uuid(),
                AllowedFilter::dynamic('tenant_membership_id')->uuid(),
                AllowedFilter::dynamic('date')->date(),
                AllowedFilter::callbackClean('payroll_month', function (Builder $query, mixed $value): void {
                    if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
                        $query->whereDate('expenses.payroll_month', $value.'-01');
                    }
                }),
                AllowedFilter::callbackClean('undocumented', function (Builder $query, mixed $value): void {
                    $query->where('expenses.is_undocumented', filter_var($value, FILTER_VALIDATE_BOOLEAN));
                }),
            );
    }
}
