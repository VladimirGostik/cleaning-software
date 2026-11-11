<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Contracts\ContractFileUpsertData;
use App\Data\Contracts\TokenContractFileData;
use App\Data\Contracts\TokenContractResultData;
use App\Data\Expenses\TokenExpenseReceiptData;
use App\Data\Expenses\TokenExpenseReceiptResultData;
use App\Data\Invoices\TokenPaymentImportData;
use App\Data\Invoices\TokenPaymentPreviewData;
use App\Enums\ActionChannelEnum;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\ToolAudit;
use App\Services\Ai\ToolContext;
use App\Services\ContractService;
use App\Services\Documents\ContractFileService;
use App\Services\ExpenseService;
use App\Services\InvoicePaymentImportService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class TokenUploadController extends Controller
{
    public function __construct(
        private readonly ToolContext $context,
        private readonly ToolAudit $audit,
        private readonly InvoicePaymentImportService $imports,
        private readonly ExpenseService $expenses,
        private readonly ContractService $contracts,
        private readonly ContractFileService $contractFiles,
    ) {}

    public function paymentsPreview(TokenPaymentPreviewData $data, Request $request): JsonResponse
    {
        $result = $this->audited($request, 'upload.invoice_payments.preview', $data->company_id, $this->fileStats($data->files), function (User $user) use ($data): array {
            $preview = $this->context->run($user, $data->company_id, function () use ($data, $user) {
                Gate::forUser($user)->authorize('importPayments', Invoice::class);

                $preview = $this->imports->preview($data->files, $user);
                $this->imports->assertAccountsMatch($preview);

                return $preview->withoutFreeText();
            });

            return [$preview, []];
        });

        return response()->json($result);
    }

    public function paymentsImport(TokenPaymentImportData $data, Request $request): JsonResponse
    {
        $arguments = [...$this->fileStats($data->files), 'exclude_count' => count($data->exclude_references)];

        $result = $this->audited($request, 'upload.invoice_payments.import', $data->company_id, $arguments, function (User $user) use ($data): array {
            $result = $this->context->run($user, $data->company_id, function () use ($data, $user) {
                Gate::forUser($user)->authorize('importPayments', Invoice::class);

                return $this->imports->importServerMatches($data->files, $data->exclude_references, $user);
            });

            return [$result->withoutFreeText(), ['recorded' => $result->recorded_count]];
        });

        return response()->json($result);
    }

    public function expenseReceipt(TokenExpenseReceiptData $data, Request $request): JsonResponse
    {
        $arguments = [...$this->fileStats([$data->file]), 'expense_ids_count' => count($data->expense_ids)];

        $result = $this->audited($request, 'upload.expense_receipt', $data->company_id, $arguments, function (User $user) use ($data): array {
            $attached = $this->context->run($user, $data->company_id, function () use ($data, $user): array {
                $found = Expense::query()->whereIn('id', $data->expense_ids)->get()->keyBy('id');

                $ordered = array_map(
                    static fn (string $id): Expense => $found->get($id) ?? abort(404),
                    $data->expense_ids,
                );

                foreach ($ordered as $expense) {
                    Gate::forUser($user)->authorize('update', $expense);
                }

                return $this->expenses->attachReceiptToMany($ordered, $data->file);
            });

            return [
                new TokenExpenseReceiptResultData(array_map(static fn (Expense $expense): string => $expense->id, $attached), $data->file->getClientOriginalName()),
                ['attached' => count($attached)],
            ];
        });

        return response()->json($result);
    }

    public function contractStore(ContractFileUpsertData $details, TokenContractFileData $data, Request $request): JsonResponse
    {
        $arguments = [
            ...$this->fileStats([$data->file]),
            'category' => $details->category->value,
            'contractable_type' => $details->contractable_type->value,
        ];

        $result = $this->audited($request, 'upload.contract', $data->company_id, $arguments, function (User $user) use ($details, $data): array {
            $result = $this->context->run($user, $data->company_id, function () use ($details, $data, $user): TokenContractResultData {
                Gate::forUser($user)->authorize('create', Contract::class);

                return $this->contracts->importFromUpload($details, $data->file, $user);
            });

            return [$result, ['created' => $result->created]];
        });

        return response()->json($result, $result->created ? 201 : 200);
    }

    public function contractAttachmentStore(TokenContractFileData $data, Request $request): JsonResponse
    {
        $result = $this->audited($request, 'upload.contract_attachment', $data->company_id, $this->fileStats([$data->file]), function (User $user) use ($data): array {
            $result = $this->context->run($user, $data->company_id, function () use ($data, $user): TokenContractResultData {
                $contract = Contract::query()->findOrFail($data->contract_id);

                Gate::forUser($user)->authorize('manageFiles', $contract);

                [$media, $created] = $this->contractFiles->addAttachment($contract, $data->file, $data->title, $user);

                return new TokenContractResultData($contract->id, $media->uuid, $created, route('contracts.show', ['contract' => $contract->id]));
            });

            return [$result, ['created' => $result->created]];
        });

        return response()->json($result, $result->created ? 201 : 200);
    }

    /**
     * @template T of object
     *
     * @param  array<string, mixed>  $arguments
     * @param  Closure(User): array{0: T, 1: array<string, mixed>}  $work
     * @return T
     */
    private function audited(Request $request, string $tool, string $companyId, array $arguments, Closure $work): object
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        try {
            Gate::forUser($user)->authorize('actIn', [Tenant::class, $companyId]);
        } catch (AuthorizationException $exception) {
            $this->audit->record($user, $tool, null, ActionChannelEnum::Mcp, [], 'denied');

            throw $exception;
        }

        try {
            [$result, $extra] = $work($user);
        } catch (Throwable $exception) {
            $this->audit->record($user, $tool, $companyId, ActionChannelEnum::Mcp, $arguments, 'error', ['reason' => class_basename($exception)]);

            throw $exception;
        }

        $this->audit->record($user, $tool, $companyId, ActionChannelEnum::Mcp, $arguments, 'ok', $extra);

        return $result;
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return array{files_count: int, bytes: int}
     */
    private function fileStats(array $files): array
    {
        return [
            'files_count' => count($files),
            'bytes' => array_sum(array_map(static fn (UploadedFile $file): int => (int) $file->getSize(), $files)),
        ];
    }
}
