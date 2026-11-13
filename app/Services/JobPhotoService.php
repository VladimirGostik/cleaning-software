<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Media;
use App\Models\ScheduledJob;
use App\Models\User;
use App\Rules\AllowedUploadFile;
use App\Support\MediaFiles;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final readonly class JobPhotoService
{
    private const string CONFIG = 'scheduling.work_records.photos';

    public function __construct(private DatabaseManager $db) {}

    public function add(ScheduledJob $job, UploadedFile $file, ?string $clientUuid, User $actor): Media
    {
        $resolved = AllowedUploadFile::resolve($file, self::CONFIG);

        if ($resolved === null) {
            throw ValidationException::withMessages(['file' => [__('app.job_photo_invalid_type')]]);
        }

        $clientUuid = $clientUuid === null ? null : strtolower($clientUuid);
        $maxCount = config()->integer(self::CONFIG.'.max_count');
        $stored = null;

        try {
            return $this->db->transaction(function () use ($job, $file, $clientUuid, $actor, $resolved, $maxCount, &$stored): Media {
                ScheduledJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();

                if ($clientUuid !== null) {
                    $replayed = $this->query($job)->where('custom_properties->client_uuid', $clientUuid)->first();

                    if ($replayed !== null) {
                        return $replayed;
                    }
                }

                if ($this->query($job)->count() >= $maxCount) {
                    throw ValidationException::withMessages(['file' => [__('app.job_photo_limit_reached', ['max' => $maxCount])]]);
                }

                /** @var Media $media */
                $media = $stored = $job->addMedia($file)
                    ->preservingOriginal()
                    ->usingName(MediaFiles::cleanName($file->getClientOriginalName()))
                    ->usingFileName(Str::uuid()->toString().'.'.$resolved['extension'])
                    ->withCustomProperties(array_filter([
                        'uploaded_by_user_id' => $actor->id,
                        'uploaded_by_name' => $actor->name,
                        'client_uuid' => $clientUuid,
                    ]))
                    ->toMediaCollection(ScheduledJob::PHOTOS_COLLECTION);

                $media->mime_type = $resolved['mime'];
                $media->save();

                return $media;
            });
        } catch (Throwable $e) {
            $stored?->delete();

            throw $e;
        }
    }

    public function find(ScheduledJob $job, string $uuid): Media
    {
        return $this->query($job)->where('uuid', $uuid)->firstOrFail();
    }

    public function stream(Media $photo): StreamedResponse
    {
        /** @var list<string> $inline */
        $inline = config(self::CONFIG.'.inline_mimes');

        return MediaFiles::stream($photo, $photo->name, $inline);
    }

    /** @return MorphMany<Media, ScheduledJob> */
    private function query(ScheduledJob $job): MorphMany
    {
        /** @var MorphMany<Media, ScheduledJob> */
        return $job->media()->where('collection_name', ScheduledJob::PHOTOS_COLLECTION);
    }
}
