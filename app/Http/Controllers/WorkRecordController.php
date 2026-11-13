<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Schedule\WorkRecordAdminStoreData;
use App\Data\Schedule\WorkRecordUpdateData;
use App\Models\CleaningObject;
use App\Models\ScheduledJob;
use App\Models\User;
use App\Services\WorkRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class WorkRecordController extends Controller
{
    public function __construct(private readonly WorkRecordService $records) {}

    #[Authorize('createWorkRecord', [ScheduledJob::class, 'object'])]
    public function store(WorkRecordAdminStoreData $data, CleaningObject $object): RedirectResponse
    {
        $this->records->recordFor($object, $data);

        return back()->with('success', __('app.work_record_created'));
    }

    #[Authorize('updateWorkRecord', 'job')]
    public function update(WorkRecordUpdateData $data, ScheduledJob $job, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->records->correct($job, $data, $actor);

        return back()->with('success', __('app.work_record_updated'));
    }

    #[Authorize('deleteWorkRecord', 'job')]
    public function destroy(ScheduledJob $job, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $object = $job->cleaningObject;
        $this->records->delete($job, $actor);

        $redirect = parse_url(url()->previous(), PHP_URL_PATH) === route('jobs.show', $job, absolute: false)
            ? ($object !== null && $actor->can('view', $object) ? to_route('objects.show', $object) : to_route('jobs.index'))
            : back();

        return $redirect->with('success', __('app.work_record_deleted'));
    }
}
