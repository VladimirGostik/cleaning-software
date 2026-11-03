<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ActivityLogDetailData;
use App\Data\ActivityLogListItemData;
use App\Enums\NavIconEnum;
use App\Enums\PermissionEnum;
use App\Models\Activity;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\ActivityRevertService;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class AuditLogController extends Controller
{
    #[Authorize('viewAny', Activity::class)]
    #[NavItem(label: 'app.audit_logs', route: 'audit-logs.index', icon: NavIconEnum::AuditLogs, permission: PermissionEnum::ViewAuditLogs->value, group: 'settings', order: 50)]
    public function index(Request $request, ActivityRevertService $reverts): Response
    {
        $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $tenantId = current_tenant_id();

        $items = QueryBuilder::for(Activity::visibleInTenant($tenantId)->with('causer'))
            ->allowedFilters(
                AllowedFilter::callbackClean('search', function (Builder $query, mixed $value) use ($op): void {
                    if (blank($value) || ! is_scalar($value)) {
                        return;
                    }

                    $like = '%'.Filters::escapeLikeValue((string) $value).'%';

                    $query->where(function (Builder $q) use ($like, $op): void {
                        $q->where('description', $op, $like)
                            ->orWhere('log_name', $op, $like)
                            ->orWhereHas('causer', fn (Builder $q2) => $q2->where('name', $op, $like)
                                ->orWhere('email', $op, $like));
                    });
                }),
                AllowedFilter::dynamic('subject_type'),
                AllowedFilter::dynamic('created_at')->date(),
            )
            ->allowedSorts(
                'created_at',
                'description',
                AllowedSort::callback('causer_name', fn (Builder $query, bool $descending) => $query->leftJoin('users as causer_user', 'causer_id', '=', 'causer_user.id')
                    ->orderBy('causer_user.name', $descending ? 'desc' : 'asc')),
            )
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        /** @var User $actor */
        $actor = $request->user();
        $revert = $reverts->describeMany($items->getCollection(), $actor);
        $labels = $reverts->subjectLabels($items->getCollection());
        $items->through(fn (Activity $a) => ActivityLogListItemData::fromModel($a, $revert[$a->id], $labels[$a->id] ?? null));

        return Inertia::render('AuditLogs/Index', [
            'activities' => $items,
            'filters' => $request->query(),
        ]);
    }

    #[Authorize('view', 'activity')]
    public function show(Request $request, Activity $activity, ActivityRevertService $reverts): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $revert = $reverts->describe($activity, $actor);
        $label = $reverts->subjectLabels(collect([$activity]))[$activity->id] ?? null;

        return Inertia::render('AuditLogs/Show', [
            'activity' => ActivityLogDetailData::fromModel($activity, $revert, $label, $reverts->changes($activity)),
        ]);
    }

    #[Authorize('revert', 'activity')]
    public function revert(Request $request, Activity $activity, ActivityRevertService $reverts): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $reverts->revert($activity, $actor);

        return back()->with('success', __('app.audit_log_reverted'));
    }
}
