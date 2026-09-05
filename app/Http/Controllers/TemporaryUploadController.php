<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\StoreTemporaryUploadData;
use App\Models\TemporaryUpload;
use App\Models\User;
use App\Services\TemporaryUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class TemporaryUploadController extends Controller
{
    public function __construct(private readonly TemporaryUploadService $service) {}

    #[Authorize('create', TemporaryUpload::class)]
    public function store(StoreTemporaryUploadData $data, Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        $media = $this->service->store(
            file: $data->file,
            user: $user,
            sessionId: $request->session()->getId(),
        );

        return response()->json([
            'uuid' => $media->uuid,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $media->getFullUrl(),
        ], 201);
    }

    #[Authorize('delete', TemporaryUpload::class)]
    public function destroy(string $uuid, Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        $this->service->delete(
            uuid: $uuid,
            user: $user,
            sessionId: $request->session()->getId(),
        );

        return response()->noContent();
    }
}
