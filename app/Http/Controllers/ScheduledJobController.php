<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Contracts\MembershipOptionData;
use App\Data\Objects\ObjectOptionData;
use App\Data\Schedule\JobAssignData;
use App\Data\Schedule\JobDetailData;
use App\Data\Schedule\JobIndexFilterData;
use App\Data\Schedule\JobListItemData;
use App\Data\Schedule\JobUpsertData;
use App\Data\Schedule\WorkBreakdownDetailData;
use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Models\ScheduledJob;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\JobService;
use App\Services\ObjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

final class ScheduledJobController extends Controller
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly ObjectService $objects,
    ) {}

    #[Authorize('viewAny', ScheduledJob::class)]
    public function index(JobIndexFilterData $filter, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $jobs = $this->jobService->paginate($filter, $actor);

        return Inertia::render('Schedule/Index', [
            'jobs' => JobListItemData::collect(
                $jobs->through(fn (ScheduledJob $job) => JobListItemData::fromModel($job)),
                PaginatedDataCollection::class,
            ),
            'filters' => $filter->toArray(),
            'statusOptions' => JobStatusEnum::options(),
            'typeOptions' => JobTypeEnum::options(),
        ]);
    }

    #[Authorize('create', ScheduledJob::class)]
    public function create(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $tenantId = app('current_tenant_id');

        return Inertia::render('Schedule/Create', [
            'typeOptions' => JobTypeEnum::options(),
            'objectOptions' => ObjectOptionData::collect(
                $this->objects->optionsVisibleTo($actor),
                DataCollection::class,
            ),
            'membershipOptions' => MembershipOptionData::collect(
                TenantMembership::with('user:id,name,email')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->get()
                    ->map(fn (TenantMembership $m) => MembershipOptionData::fromModel($m)),
                DataCollection::class,
            ),
        ]);
    }

    #[Authorize('create', ScheduledJob::class)]
    public function store(JobUpsertData $data): RedirectResponse
    {
        $job = $this->jobService->create($data);

        return to_route('jobs.show', $job)->with('success', __('app.schedule.job_created'));
    }

    #[Authorize('view', 'job')]
    public function show(ScheduledJob $job, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $tenantId = app('current_tenant_id');

        $job->load(['cleaningObject.client', 'assignedMembership.user', 'workBreakdown.tasks', 'contract']);

        $can = [
            'update' => $actor->can('update', $job),
            'assign' => $actor->can('assign', $job),
            'cancel' => $actor->can('cancel', $job),
        ];

        return Inertia::render('Schedule/Show', [
            'job' => JobDetailData::fromModel($job, $can),
            'membershipOptions' => $can['assign']
                ? MembershipOptionData::collect(
                    TenantMembership::with('user:id,name,email')
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true)
                        ->get()
                        ->map(fn (TenantMembership $m) => MembershipOptionData::fromModel($m)),
                    DataCollection::class,
                )
                : MembershipOptionData::collect([], DataCollection::class),
            'workBreakdown' => $job->workBreakdown !== null
                ? WorkBreakdownDetailData::fromModel($job->workBreakdown)
                : null,
        ]);
    }

    #[Authorize('update', 'job')]
    public function edit(ScheduledJob $job, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $tenantId = app('current_tenant_id');

        $job->load(['cleaningObject.client', 'assignedMembership.user']);

        return Inertia::render('Schedule/Edit', [
            'job' => JobDetailData::fromModel($job),
            'typeOptions' => JobTypeEnum::options(),
            'objectOptions' => ObjectOptionData::collect(
                $this->objects->optionsVisibleTo($actor),
                DataCollection::class,
            ),
            'membershipOptions' => MembershipOptionData::collect(
                TenantMembership::with('user:id,name,email')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->get()
                    ->map(fn (TenantMembership $m) => MembershipOptionData::fromModel($m)),
                DataCollection::class,
            ),
        ]);
    }

    #[Authorize('update', 'job')]
    public function update(ScheduledJob $job, JobUpsertData $data): RedirectResponse
    {
        $this->jobService->update($job, $data);

        return to_route('jobs.show', $job)->with('success', __('app.schedule.job_updated'));
    }

    #[Authorize('assign', 'job')]
    public function assign(ScheduledJob $job, JobAssignData $data): RedirectResponse
    {
        $this->jobService->assign($job, $data);

        return to_route('jobs.show', $job)->with('success', __('app.schedule.job_assigned'));
    }

    #[Authorize('cancel', 'job')]
    public function cancel(ScheduledJob $job): RedirectResponse
    {
        $this->jobService->cancel($job);

        return to_route('jobs.show', $job)->with('success', __('app.schedule.job_cancelled'));
    }
}
