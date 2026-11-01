<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Employees\EmployeeDocumentUploadData;
use App\Models\Document;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Documents\EmployeeDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EmployeeDocumentController extends Controller
{
    public function __construct(private readonly EmployeeDocumentService $documents) {}

    #[Authorize('attach', [Document::class, 'employee'])]
    public function store(EmployeeDocumentUploadData $data, TenantMembership $employee): RedirectResponse
    {
        $this->documents->attach($employee, $data);

        return to_route('employees.show', $employee)->with('success', __('app.employee_document_uploaded'));
    }

    #[Authorize('download', 'document')]
    public function download(TenantMembership $employee, Document $document, Request $request): StreamedResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return $this->documents->stream($document, $actor);
    }

    #[Authorize('delete', 'document')]
    public function destroy(TenantMembership $employee, Document $document): RedirectResponse
    {
        $this->documents->delete($document);

        return to_route('employees.show', $employee)->with('success', __('app.employee_document_deleted'));
    }
}
