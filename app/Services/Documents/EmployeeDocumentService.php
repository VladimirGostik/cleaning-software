<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\Employees\EmployeeDocumentUploadData;
use App\Models\Document;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class EmployeeDocumentService
{
    public function __construct(private DatabaseManager $db) {}

    public function attach(TenantMembership $employee, EmployeeDocumentUploadData $data): Document
    {
        return $this->db->transaction(function () use ($employee, $data): Document {
            $document = Document::create([
                'tenant_id' => $employee->tenant_id,
                'documentable_type' => $employee->getMorphClass(),
                'documentable_id' => $employee->id,
                'type' => $data->type,
                'title' => $data->title ?? $data->file->getClientOriginalName(),
                'show_header' => false,
                'show_footer' => false,
            ]);

            $document->addMedia($data->file)->toMediaCollection('file');

            return $document;
        });
    }

    /** Hard delete: a soft-deleted ID scan would stay in the bucket with no restore UI. */
    public function delete(Document $document): void
    {
        $this->db->transaction(function () use ($document): void {
            $document->forceDelete();
        });
    }

    public function stream(Document $document, User $actor): StreamedResponse
    {
        $media = $document->getFirstMedia('file');

        abort_if($media === null, 404);

        activity()
            ->performedOn($document)
            ->causedBy($actor)
            ->event('downloaded')
            ->log('downloaded');

        return response()->streamDownload(
            function () use ($media): void {
                /** @var resource $stream */
                $stream = $media->stream();
                fpassthru($stream);
                fclose($stream);
            },
            $media->file_name,
            [
                'Content-Type' => $media->mime_type ?? 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'attachment',
        );
    }
}
