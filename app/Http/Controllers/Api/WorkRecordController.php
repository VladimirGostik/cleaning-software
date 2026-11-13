<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Schedule\WorkRecordStoreData;
use App\Http\Controllers\Controller;
use App\Models\ScheduledJob;
use App\Models\User;
use App\Services\JobService;
use App\Services\WorkRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;

#[Group('Schedule', 'Own jobs of the signed-in person')]
#[Authenticated]
final class WorkRecordController extends Controller
{
    public function __construct(
        private readonly WorkRecordService $records,
        private readonly JobService $jobs,
    ) {}

    #[Authorize('recordAnywhere', ScheduledJob::class)]
    #[Endpoint('Work record context', 'Objects the caller may record work on in every company with mobile access, with their per-visit item names (no prices), and the limits.')]
    public function context(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->records->context($actor));
    }

    #[Authorize('recordAnywhere', ScheduledJob::class)]
    #[Endpoint('Record work', 'Creates a completed one-off job with the ticked items. A known client_uuid returns the stored record with 200.')]
    public function store(WorkRecordStoreData $data, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $job = $this->records->record($data, $actor);

        return response()->json($this->jobs->presentMine($job), $job->wasRecentlyCreated ? 201 : 200);
    }
}
