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
use App\Models\CleaningObject;
use App\Models\ScheduledJob;
use App\Models\TenantMembership;
use App\Services\JobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;

final class ScheduledJobController extends Controller
{
    public function __construct(private readonly JobService $jobService) {}

    #[Authorize('viewAny', ScheduledJob::class)]
    public function index(JobIndexFilterData $filter): Response
    {
        $jobs = $this->jobService->paginate($filter);

        return Inertia::render('Schedule/Index', [
            'jobs' => JobListItemData::collect($jobs->through(fn (ScheduledJob $job) => JobListItemData::fromModel($job))),
            'filters' => $filter->toArray(),
            'statusOptions' => JobStatusEnum::options(),
            'typeOptions' => JobTypeEnum::options(),
        ]);
    }

    #[Authorize('create', ScheduledJob::class)]
    public function create(): Response
    {
        $tenantId = app('current_tenant_id');

        return Inertia::render('Schedule/Create', [
            'typeOptions' => JobTypeEnum::options(),
            'objectOptions' => ObjectOptionData::collect(
                CleaningObject::query()
                    ->select(['id', 'name', 'client_id'])
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(),
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
    public function show(ScheduledJob $job): Response
    {
        $tenantId = app('current_tenant_id');

        $job->load(['cleaningObject.client', 'assignedMembership.user', 'workBreakdown.tasks', 'contract']);

        return Inertia::render('Schedule/Show', [
            'job' => JobDetailData::fromModel($job, [
                'update' => request()->user()?->can('update', $job) ?? false,
                'assign' => request()->user()?->can('assign', $job) ?? false,
                'cancel' => request()->user()?->can('cancel', $job) ?? false,
            ]),
            'membershipOptions' => MembershipOptionData::collect(
                TenantMembership::with('user:id,name,email')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->get()
                    ->map(fn (TenantMembership $m) => MembershipOptionData::fromModel($m)),
                DataCollection::class,
            ),
            'workBreakdown' => $job->workBreakdown !== null
                ? WorkBreakdownDetailData::fromModel($job->workBreakdown)
                : null,
        ]);
    }

    #[Authorize('update', 'job')]
    public function edit(ScheduledJob $job): Response
    {
        $tenantId = app('current_tenant_id');

        $job->load(['cleaningObject.client', 'assignedMembership.user']);

        return Inertia::render('Schedule/Edit', [
            'job' => JobDetailData::fromModel($job),
            'typeOptions' => JobTypeEnum::options(),
            'objectOptions' => ObjectOptionData::collect(
                CleaningObject::query()
                    ->select(['id', 'name', 'client_id'])
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(),
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
