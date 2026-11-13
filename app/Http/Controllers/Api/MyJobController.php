<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Schedule\MyJobData;
use App\Data\Schedule\MyJobsFilterData;
use App\Http\Controllers\Controller;
use App\Models\ScheduledJob;
use App\Models\User;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;

#[Group('Schedule', 'Own jobs of the signed-in person')]
#[Authenticated]
final class MyJobController extends Controller
{
    public function __construct(private readonly JobService $jobs) {}

    #[Endpoint('My jobs', 'Jobs assigned to the caller in every company with mobile access, cancelled jobs excluded.')]
    #[QueryParam('from', 'string', 'First day, YYYY-MM-DD.', example: '2026-11-01')]
    #[QueryParam('to', 'string', 'Last day, YYYY-MM-DD, at most 62 days after from.', example: '2026-11-30')]
    #[Authorize('viewOwnAcrossCompanies', ScheduledJob::class)]
    public function index(MyJobsFilterData $filter, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json(MyJobData::collect($this->jobs->myJobs($filter, $actor)));
    }

    #[Authorize('markDone', 'job')]
    #[Endpoint('Mark a job done', 'Planned to completed in one step; a job that is already completed is returned unchanged.')]
    public function complete(ScheduledJob $job): JsonResponse
    {
        return response()->json($this->jobs->presentMine($this->jobs->complete($job)));
    }
}
