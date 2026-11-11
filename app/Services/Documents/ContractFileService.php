<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\Contracts\ContractFileData;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Media;
use App\Models\User;
use App\Rules\AllowedUploadFile;
use App\Services\TemporaryUploadService;
use App\Support\MediaFiles;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final readonly class ContractFileService
{
    private const string CONFIG = 'documents.contract_files';

    public function __construct(private DatabaseManager $db, private TemporaryUploadService $uploads) {}

    public function attachStagedMain(Document $document, string $uuid, User $actor, string $sessionId, ?string $sha256 = null): Media
    {
        $staged = $this->uploads->findOwned($uuid, $actor, $sessionId);
        $extension = pathinfo($staged->file_name, PATHINFO_EXTENSION);
        $resolved = AllowedUploadFile::resolveDetected((string) $staged->mime_type, $extension, self::CONFIG);

        if ($resolved === null) {
            throw ValidationException::withMessages(['file_uuid' => [__('app.contract_file_invalid_type')]]);
        }

        $sha256 ??= $this->hashStream($staged);
        $original = MediaFiles::cleanName($staged->file_name);

        $media = $this->uploads->moveToModel($document, 'file', $uuid, $actor, $sessionId);

        $media->name = $original;
        $media->mime_type = $resolved['mime'];
        $media->setCustomProperty('sha256', $sha256);
        $media->setCustomProperty('original_name', $original);
        $media->setCustomProperty('uploaded_by_user_id', $actor->id);
        $media->setCustomProperty('uploaded_by_name', $actor->name);
        $media->save();

        return $media;
    }

    public function attachUploadedMain(Document $document, UploadedFile $file, User $actor, ?string $sha256 = null): Media
    {
        $resolved = $this->resolve($file, 'file');
        $original = MediaFiles::cleanName($file->getClientOriginalName());

        /** @var Media $media */
        $media = $document->addMedia($file)
            ->usingName($original)
            ->usingFileName(Str::uuid()->toString().'.'.$resolved['extension'])
            ->withCustomProperties([
                'sha256' => $sha256 ?? $this->hashUpload($file),
                'original_name' => $original,
                'uploaded_by_user_id' => $actor->id,
                'uploaded_by_name' => $actor->name,
            ])
            ->toMediaCollection('file');

        $media->mime_type = $resolved['mime'];
        $media->save();

        return $media;
    }

    public function hashStaged(string $uuid, User $actor, string $sessionId): string
    {
        return $this->hashStream($this->uploads->findOwned($uuid, $actor, $sessionId));
    }

    public function hashUpload(UploadedFile $file): string
    {
        return (string) hash_file('sha256', $file->getRealPath());
    }

    public function findContractByHash(string $sha256): ?Contract
    {
        return Contract::query()->where('file_sha256', $sha256)->first();
    }

    /** @return array{0: Media, 1: bool} */
    public function addAttachment(Contract $contract, UploadedFile $file, ?string $title, User $actor): array
    {
        $resolved = $this->resolve($file, 'file');
        $sha256 = $this->hashUpload($file);
        $maxCount = config()->integer(self::CONFIG.'.max_attachments');
        $stored = null;

        try {
            return $this->db->transaction(function () use ($contract, $file, $title, $actor, $resolved, $sha256, $maxCount, &$stored): array {
                Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

                $document = $this->document($contract);

                $existing = $document->media()
                    ->whereIn('collection_name', ['file', Document::ATTACHMENTS_COLLECTION])
                    ->get()
                    ->first(fn (SpatieMedia $media): bool => $media->getCustomProperty('sha256') === $sha256);

                if ($existing instanceof Media) {
                    return [$existing, false];
                }

                if ($this->attachmentsQuery($document)->count() >= $maxCount) {
                    throw ValidationException::withMessages(['file' => [__('app.contract_attachment_limit_reached', ['max' => $maxCount])]]);
                }

                $original = MediaFiles::cleanName($file->getClientOriginalName());

                /** @var Media $media */
                $media = $stored = $document->addMedia($file)
                    ->usingName($title !== null && trim($title) !== '' ? trim($title) : $original)
                    ->usingFileName(Str::uuid()->toString().'.'.$resolved['extension'])
                    ->withCustomProperties([
                        'sha256' => $sha256,
                        'original_name' => $original,
                        'uploaded_by_user_id' => $actor->id,
                        'uploaded_by_name' => $actor->name,
                    ])
                    ->toMediaCollection(Document::ATTACHMENTS_COLLECTION);

                $media->mime_type = $resolved['mime'];
                $media->save();

                $this->audit($contract, $actor, 'attachment_added', [
                    'title' => $media->name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'sha256' => $sha256,
                ]);

                return [$media, true];
            });
        } catch (Throwable $e) {
            $stored?->delete();

            throw $e;
        }
    }

    public function find(Contract $contract, string $uuid): Media
    {
        /** @var Media */
        return $this->document($contract)->media()
            ->whereIn('collection_name', ['file', Document::ATTACHMENTS_COLLECTION])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function deleteAttachment(Contract $contract, Media $attachment, User $actor): void
    {
        abort_unless($attachment->collection_name === Document::ATTACHMENTS_COLLECTION, 404);

        $this->db->transaction(function () use ($contract, $attachment, $actor): void {
            $properties = [
                'title' => $attachment->name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'sha256' => $attachment->getCustomProperty('sha256'),
            ];

            $attachment->delete();

            $this->audit($contract, $actor, 'attachment_deleted', $properties);
        });
    }

    /** @param array<string, mixed> $properties */
    public function auditMainReplaced(Contract $contract, User $actor, array $properties): void
    {
        $this->audit($contract, $actor, 'main_file_replaced', $properties);
    }

    public function stream(Media $media): StreamedResponse
    {
        /** @var list<string> $inline */
        $inline = config(self::CONFIG.'.inline_mimes');
        $original = $media->getCustomProperty('original_name');

        return MediaFiles::stream($media, is_string($original) && $original !== '' ? $original : $media->file_name, $inline);
    }

    /** @return array{main: ?ContractFileData, attachments: list<ContractFileData>} */
    public function present(Contract $contract, User $actor): array
    {
        if (! Gate::forUser($actor)->allows('viewBody', $contract)) {
            return ['main' => null, 'attachments' => []];
        }

        $document = $contract->document;

        if ($document === null) {
            return ['main' => null, 'attachments' => []];
        }

        $canDelete = Gate::forUser($actor)->allows('manageFiles', $contract);
        $main = $document->getFirstMedia('file');

        return [
            'main' => $main instanceof Media ? ContractFileData::fromMedia($main, $contract->id, false) : null,
            'attachments' => array_values($this->attachmentsQuery($document)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (Media $media): ContractFileData => ContractFileData::fromMedia($media, $contract->id, $canDelete))
                ->all()),
        ];
    }

    /** @return array{mime: string, extension: string} */
    private function resolve(UploadedFile $file, string $field): array
    {
        $resolved = AllowedUploadFile::resolve($file, self::CONFIG);

        if ($resolved === null) {
            throw ValidationException::withMessages([$field => [__('app.contract_file_invalid_type')]]);
        }

        return $resolved;
    }

    private function hashStream(Media $media): string
    {
        /** @var resource $stream */
        $stream = $media->stream();
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    private function document(Contract $contract): Document
    {
        $document = $contract->document;

        abort_if($document === null, 404);

        return $document;
    }

    /** @return MorphMany<Media, Document> */
    private function attachmentsQuery(Document $document): MorphMany
    {
        /** @var MorphMany<Media, Document> */
        return $document->media()->where('collection_name', Document::ATTACHMENTS_COLLECTION);
    }

    /** @param array<string, mixed> $properties */
    private function audit(Contract $contract, User $actor, string $event, array $properties): void
    {
        activity()
            ->performedOn($contract)
            ->causedBy($actor)
            ->event($event)
            ->withProperties($properties)
            ->log($event);
    }
}
