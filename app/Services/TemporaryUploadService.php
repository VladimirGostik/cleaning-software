<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class TemporaryUploadService
{
    public function store(UploadedFile $file, ?User $user, string $sessionId): Media
    {
        return DB::transaction(function () use ($file, $user, $sessionId): Media {
            // Authenticated users: look up by user_id (session can change between requests)
            // Guest users: look up by session_id
            if ($user !== null) {
                /** @var TemporaryUpload $temporaryUpload */
                $temporaryUpload = TemporaryUpload::firstOrCreate(
                    ['user_id' => $user->id],
                    ['session_id' => $sessionId],
                );
            } else {
                /** @var TemporaryUpload $temporaryUpload */
                $temporaryUpload = TemporaryUpload::firstOrCreate(
                    ['session_id' => $sessionId, 'user_id' => null],
                );
            }

            return $temporaryUpload
                ->addMedia($file)
                ->toMediaCollection('default');
        });
    }

    public function moveToModel(HasMedia $model, string $collection, string $uuid): Media
    {
        return DB::transaction(function () use ($model, $collection, $uuid): Media {
            /** @var Media $media */
            $media = Media::query()
                ->where('uuid', $uuid)
                ->where('model_type', (new TemporaryUpload)->getMorphClass())
                ->firstOrFail();

            $moved = $media->move($model, $collection);

            // Clean up empty TemporaryUpload records
            $temporaryUpload = TemporaryUpload::find($media->model_id);
            if ($temporaryUpload instanceof TemporaryUpload && $temporaryUpload->getMedia()->isEmpty()) {
                $temporaryUpload->delete();
            }

            return $moved;
        });
    }

    public function delete(string $uuid, ?User $user, string $sessionId): void
    {
        DB::transaction(function () use ($uuid, $user, $sessionId): void {
            $ownedIds = TemporaryUpload::query()
                ->where('session_id', $sessionId)
                ->when($user !== null, fn ($q) => $q->orWhere('user_id', $user->id))
                ->pluck('id');

            Media::query()
                ->where('uuid', $uuid)
                ->where('model_type', (new TemporaryUpload)->getMorphClass())
                ->whereIn('model_id', $ownedIds)
                ->firstOrFail()
                ->delete();
        });
    }

    public function purgeOlderThan(int $hours = 24): int
    {
        return DB::transaction(function () use ($hours): int {
            $stale = TemporaryUpload::query()
                ->where('created_at', '<', now()->subHours($hours))
                ->get();

            foreach ($stale as $upload) {
                $upload->delete(); // InteractsWithMedia deletes associated media files
            }

            return $stale->count();
        });
    }
}
