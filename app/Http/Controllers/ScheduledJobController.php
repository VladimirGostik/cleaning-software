<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Contracts\MembershipOptionData;
use App\Data\Schedule\JobAssignData;
use App\Data\Schedule\JobCalendarFilterData;
use App\Data\Schedule\JobCalendarItemData;
use App\Data\Schedule\JobDetailData;
use App\Data\Schedule\JobFormContextData;
use App\Data\Schedule\JobStoreData;
use App\Data\Schedule\JobUpdateData;
use App\Data\Schedule\WorkBreakdownDetailData;
use App\Enums\PermissionEnum;
use App\Models\ScheduledJob;
use App\Models\TenantMembership;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\JobService;
use App\Services\ObjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class ScheduledJobController extends Controller
{
    public function __construct(
        private readonly JobService $jobs,
        private readonly ObjectService $objects,
    ) {}

    #[Authorize('viewAny', ScheduledJob::class)]
    #[NavItem(label: 'app.schedule', route: 'jobs.index', icon: 'CalendarDaysIcon', permission: PermissionEnum::ViewSchedule->value, order: 32)]
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Schedule/Index', [
            'jobs' => $this->jobs->paginate($request, $actor),
            'filters' => $request->query(),
            'filterOptions' => [
                'objects' => $this->objects->optionsVisibleTo($actor),
                'memberships' => $actor->can(PermissionEnum::ViewAllSchedule->value) ? $this->membershipOptions() : [],
            ],
        ]);
    }

    #[Authorize('viewAny', ScheduledJob::class)]
    public function calendar(JobCalendarFilterData $filter, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(JobCalendarItemData::collect($this->jobs->calendar($filter, $actor)));
    }

    #[Authorize('create', ScheduledJob::class)]
    public function create(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Schedule/Create', [
            'context' => $this->formContext($actor),
        ]);
    }

    #[Authorize('create', ScheduledJob::class)]
    public function store(JobStoreData $data, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $job = $this->jobs->create($data, $actor);

        return to_route('jobs.show', $job)->with('success', __('app.job_created'));
    }

    #[Authorize('view', 'job')]
    public function show(ScheduledJob $job, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $job->load([
            'cleaningObject.client',
            'assignedMembership.user',
            'workBreakdown.tasks',
            'workBreakdown.contract:id,title,status',
            'workBreakdownTask',
            'contract:id,title',
        ]);

        $can = [
            'update' => $actor->can('update', $job),
            'assign' => $actor->can('assign', $job),
            'cancel' => $actor->can('cancel', $job),
        ];

        return Inertia::render('Schedule/Show', [
            'job' => JobDetailData::fromModel($job, $can),
            'membershipOptions' => $can['assign'] ? $this->membershipOptions() : [],
            'workBreakdown' => $job->workBreakdown !== null ? WorkBreakdownDetailData::fromModel($job->workBreakdown) : null,
        ]);
    }

    #[Authorize('update', 'job')]
    public function edit(ScheduledJob $job, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $job->load(['cleaningObject.client', 'assignedMembership.user']);

        return Inertia::render('Schedule/Edit', [
            'job' => JobDetailData::fromModel($job),
            'context' => $this->formContext($actor),
        ]);
    }

    #[Authorize('update', 'job')]
    public function update(JobUpdateData $data, ScheduledJob $job, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->jobs->update($job, $data, $actor);

        return to_route('jobs.show', $job)->with('success', __('app.job_updated'));
    }

    #[Authorize('assign', 'job')]
    public function assign(JobAssignData $data, ScheduledJob $job): RedirectResponse
    {
        $this->jobs->assign($job, $data);

        return to_route('jobs.show', $job)->with('success', __('app.job_assigned'));
    }

    #[Authorize('cancel', 'job')]
    public function cancel(ScheduledJob $job): RedirectResponse
    {
        $this->jobs->cancel($job);

        return to_route('jobs.show', $job)->with('success', __('app.job_cancelled'));
    }

    private function formContext(User $actor): JobFormContextData
    {
        return new JobFormContextData(
            objects: $this->objects->optionsVisibleTo($actor),
            memberships: $actor->can(PermissionEnum::AssignCleaners->value) ? $this->membershipOptions() : [],
        );
    }

    /** @return array<int, MembershipOptionData> */
    private function membershipOptions(): array
    {
        return TenantMembership::query()
            ->with('user:id,name,email')
            ->where('tenant_id', current_tenant_id())
            ->where('is_active', true)
            ->get()
            ->map(fn (TenantMembership $membership) => MembershipOptionData::fromModel($membership))
            ->sortBy('label')
            ->values()
            ->all();
    }
}
