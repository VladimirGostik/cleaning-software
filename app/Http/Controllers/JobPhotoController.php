<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ScheduledJob;
use App\Services\JobPhotoService;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class JobPhotoController extends Controller
{
    public function __construct(private readonly JobPhotoService $photos) {}

    #[Authorize('viewWorkRecordPhoto', 'job')]
    public function show(ScheduledJob $job, string $photo): StreamedResponse
    {
        return $this->photos->stream($this->photos->find($job, $photo));
    }
}
