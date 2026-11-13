<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Schedule\JobPhotoData;
use App\Data\Schedule\JobPhotoUploadData;
use App\Http\Controllers\Controller;
use App\Models\ScheduledJob;
use App\Models\User;
use App\Services\JobPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;

#[Group('Schedule', 'Own jobs of the signed-in person')]
#[Authenticated]
final class JobPhotoController extends Controller
{
    public function __construct(private readonly JobPhotoService $photos) {}

    #[Authorize('addPhoto', 'job')]
    #[Endpoint('Add a photo to a work record', 'Multipart, one file per request, up to 5 per record. A known client_uuid on the record returns the stored photo with 200.')]
    #[BodyParam('file', 'file', 'The photo.')]
    #[BodyParam('client_uuid', 'string', 'Idempotency key of this photo.')]
    public function store(JobPhotoUploadData $data, ScheduledJob $job, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $photo = $this->photos->add($job, $data->file, $data->client_uuid, $actor);

        return response()->json(JobPhotoData::fromMedia($photo), $photo->wasRecentlyCreated ? 201 : 200);
    }
}
