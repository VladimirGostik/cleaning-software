<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Contracts\ContractAttachmentUploadData;
use App\Data\Contracts\ContractFileUpsertData;
use App\Models\Contract;
use App\Models\User;
use App\Services\ContractService;
use App\Services\Documents\ContractFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ContractFileController extends Controller
{
    public function __construct(
        private readonly ContractService $contracts,
        private readonly ContractFileService $files,
    ) {}

    #[Authorize('create', Contract::class)]
    public function store(ContractFileUpsertData $data, Request $request): RedirectResponse
    {
        $contract = $this->contracts->createFromFile($data, $this->actor($request), $request->session()->getId());

        return to_route('contracts.show', $contract)->with('success', __('app.contract_created'));
    }

    #[Authorize('update', 'contract')]
    public function update(ContractFileUpsertData $data, Contract $contract, Request $request): RedirectResponse
    {
        $this->contracts->updateFileDetails($contract, $data, $this->actor($request), $request->session()->getId());

        return to_route('contracts.show', $contract)->with('success', __('app.contract_updated'));
    }

    #[Authorize('manageFiles', 'contract')]
    public function storeAttachment(ContractAttachmentUploadData $data, Contract $contract, Request $request): RedirectResponse
    {
        [, $created] = $this->files->addAttachment($contract, $data->file, $data->title, $this->actor($request));

        return back()->with($created ? 'success' : 'info', __($created ? 'app.contract_attachment_added' : 'app.contract_attachment_exists'));
    }

    #[Authorize('manageFiles', 'contract')]
    public function destroyAttachment(Contract $contract, string $attachment, Request $request): RedirectResponse
    {
        $this->files->deleteAttachment($contract, $this->files->find($contract, $attachment), $this->actor($request));

        return back()->with('success', __('app.contract_attachment_deleted'));
    }

    #[Authorize('viewBody', 'contract')]
    public function show(Contract $contract, string $attachment): StreamedResponse
    {
        return $this->files->stream($this->files->find($contract, $attachment));
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
