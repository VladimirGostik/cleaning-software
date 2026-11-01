<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tasks\TaskAttachmentConstraintsData;
use App\Data\Tasks\TaskAttachmentData;
use App\Data\Tasks\TaskAttachmentListData;
use App\Models\Activity;
use App\Models\Media;
use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use App\Rules\TaskAttachmentFile;
use App\Services\Ai\Guard\PromptGuard;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final readonly class TaskAttachmentService
{
    private const int MAX_NAME_LENGTH = 120;

    public function __construct(private DatabaseManager $db, private TaskPolicy $policy) {}

    public function list(Task $task, User $actor, string $downloadRoute): TaskAttachmentListData
    {
        $constraints = TaskAttachmentConstraintsData::fromConfig();
        $visible = $task->isVisibleTo($actor);

        $attachments = $this->query($task)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($constraints->max_count)
            ->get()
            ->map(fn (Media $media): TaskAttachmentData => $this->present($task, $media, $actor, $downloadRoute, $visible))
            ->all();

        return new TaskAttachmentListData(
            attachments: $attachments,
            can_add: Gate::forUser($actor)->allows('update', $task),
            constraints: $constraints,
        );
    }

    public function present(Task $task, Media $media, User $actor, string $downloadRoute, ?bool $taskVisible = null): TaskAttachmentData
    {
        return TaskAttachmentData::fromMedia(
            $media,
            route($downloadRoute, ['task' => $task->id, 'attachment' => $media->uuid], absolute: false),
            $this->policy->mayDeleteAttachment($actor, $task, $media, $taskVisible ?? $task->isVisibleTo($actor)),
        );
    }

    public function add(Task $task, UploadedFile $file, User $actor): Media
    {
        $resolved = TaskAttachmentFile::resolve($file);

        if ($resolved === null) {
            throw ValidationException::withMessages(['file' => [__('app.task_attachment_invalid_type')]]);
        }

        $maxCount = config()->integer('tasks.attachments.max_count');

        $stored = null;

        try {
            return $this->db->transaction(function () use ($task, $file, $actor, $resolved, $maxCount, &$stored): Media {
                Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

                if ($this->query($task)->count() >= $maxCount) {
                    throw ValidationException::withMessages(['file' => [__('app.task_attachment_limit_reached', ['max' => $maxCount])]]);
                }

                /** @var Media $media */
                $media = $stored = $task->addMedia($file)
                    ->usingName(self::cleanName($file->getClientOriginalName()))
                    ->usingFileName(Str::uuid()->toString().'.'.$resolved['extension'])
                    ->withCustomProperties(['uploaded_by_user_id' => $actor->id, 'uploaded_by_name' => $actor->name])
                    ->toMediaCollection(Task::ATTACHMENTS_COLLECTION);

                $media->mime_type = $resolved['mime'];
                $media->save();

                $this->audit($task, $actor, 'attachment_added', $media);

                return $media;
            });
        } catch (Throwable $e) {
            $stored?->delete();

            throw $e;
        }
    }

    public function find(Task $task, string $uuid): Media
    {
        return $this->query($task)->where('uuid', $uuid)->firstOrFail();
    }

    public function delete(Task $task, Media $attachment, User $actor): void
    {
        Gate::forUser($actor)->authorize('deleteAttachment', [$task, $attachment]);

        $this->db->transaction(function () use ($task, $attachment, $actor): void {
            $attachment->delete();

            $this->audit($task, $actor, 'attachment_deleted', $attachment);
        });
    }

    public function stream(Media $attachment): StreamedResponse
    {
        /** @var list<string> $inline */
        $inline = config('tasks.attachments.inline_mimes');
        $mime = (string) $attachment->mime_type;
        $name = $attachment->name;
        $fallback = trim((string) preg_replace('/[^\x20-\x7E]|[%\/\\\\]/', '_', Str::ascii($name)));

        return response()->stream(
            function () use ($attachment): void {
                /** @var resource $stream */
                $stream = $attachment->stream();
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $mime,
                'Content-Length' => (string) $attachment->size,
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    in_array($mime, $inline, true) ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                    $name,
                    $fallback === '' ? 'file' : $fallback,
                ),
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    public static function cleanName(string $original): string
    {
        $name = str_replace('\\', '/', $original);
        $slash = strrpos($name, '/');
        $name = $slash === false ? $name : substr($name, $slash + 1);

        $name = PromptGuard::stripInvisible($name);
        $name = (string) preg_replace('/\p{Cc}/u', '', $name);
        $name = self::trimSeparators($name);

        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $dot = mb_strrpos($name, '.');
            $extensionLength = $dot === false ? 0 : mb_strlen($name) - $dot;

            $name = $dot !== false && $extensionLength >= 2 && $extensionLength <= 11
                ? mb_substr($name, 0, self::MAX_NAME_LENGTH - $extensionLength).mb_substr($name, $dot)
                : mb_substr($name, 0, self::MAX_NAME_LENGTH);

            $name = self::trimSeparators($name);
        }

        return $name === '' ? 'file' : $name;
    }

    private static function trimSeparators(string $value): string
    {
        return (string) preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $value);
    }

    /** @return MorphMany<Media, Task> */
    private function query(Task $task): MorphMany
    {
        /** @var MorphMany<Media, Task> */
        return $task->media()->where('collection_name', Task::ATTACHMENTS_COLLECTION);
    }

    private function audit(Task $task, User $actor, string $event, Media $media): void
    {
        $properties = ['attachment_id' => $media->uuid, 'mime_type' => $media->mime_type, 'size' => $media->size];

        if (! $task->isGlobal()) {
            $properties['name'] = $media->name;
        }

        activity()
            ->performedOn($task)
            ->causedBy($actor)
            ->event($event)
            ->withProperties($properties)
            ->tap(function (Activity $activity) use ($task): void {
                $activity->tenant_id = $task->tenant_id;
                $activity->keepNullTenant = $task->isGlobal();
            })
            ->log($event);
    }
}
