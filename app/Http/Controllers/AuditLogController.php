<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ActivityLogDetailData;
use App\Data\ActivityLogListItemData;
use App\Enums\PermissionEnum;
use App\Models\Activity;
use App\Navigation\NavItem;
use App\Utils\AllowedFilter;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class AuditLogController extends Controller
{
    #[Authorize('viewAny', Activity::class)]
    #[NavItem(label: 'app.audit_logs', route: 'audit-logs.index', icon: 'ClipboardDocumentListIcon', permission: PermissionEnum::ViewAuditLogs->value, order: 40)]
    public function index(Request $request): Response
    {
        $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';
        $tenantId = current_tenant_id();

        $items = QueryBuilder::for(Activity::visibleInTenant($tenantId))
            ->allowedFilters(
                AllowedFilter::callbackClean('search', function ($query, $value) use ($op): void {
                    if (blank($value)) {
                        return;
                    }

                    $query->where(function ($q) use ($value, $op): void {
                        $q->where('description', $op, "%{$value}%")
                            ->orWhere('log_name', $op, "%{$value}%")
                            ->orWhereHas('causer', fn ($q2) => $q2->where('name', $op, "%{$value}%")
                                ->orWhere('email', $op, "%{$value}%"));
                    });
                }),
                AllowedFilter::dynamic('subject_type'),
                AllowedFilter::dynamic('created_at')->date(),
            )
            ->allowedSorts(
                'created_at',
                'description',
                AllowedSort::callback('causer_name', fn ($query, bool $descending) => $query->leftJoin('users as causer_user', 'causer_id', '=', 'causer_user.id')
                    ->orderBy('causer_user.name', $descending ? 'desc' : 'asc')),
            )
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (Activity $a) => ActivityLogListItemData::fromModel($a));

        return Inertia::render('AuditLogs/Index', [
            'activities' => $items,
            'filters' => $request->query(),
        ]);
    }

    #[Authorize('view', 'activity')]
    public function show(Activity $activity): Response
    {
        return Inertia::render('AuditLogs/Show', [
            'activity' => ActivityLogDetailData::fromModel($activity),
        ]);
    }
}
