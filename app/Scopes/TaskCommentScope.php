<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Fail-closed: the bound tenant's comments plus the authenticated user's comments on their own global tasks.
 *
 * @implements Scope<Model>
 */
final class TaskCommentScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
        $userId = auth()->id();

        $builder->where(function (Builder $query) use ($model, $tenantId, $userId): void {
            if ($tenantId !== null) {
                $query->where($model->qualifyColumn('tenant_id'), $tenantId);
            }

            if ($userId !== null) {
                $query->orWhere(function (Builder $global) use ($model, $userId): void {
                    $global->whereNull($model->qualifyColumn('tenant_id'))
                        ->where($model->qualifyColumn('user_id'), $userId);
                });
            }

            if ($tenantId === null && $userId === null) {
                $query->whereRaw('1 = 0');
            }
        });
    }
}
