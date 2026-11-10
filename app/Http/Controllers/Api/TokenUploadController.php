<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Expenses\TokenExpenseReceiptData;
use App\Data\Expenses\TokenExpenseReceiptResultData;
use App\Data\Invoices\TokenPaymentImportData;
use App\Data\Invoices\TokenPaymentPreviewData;
use App\Enums\ActionChannelEnum;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\ToolAudit;
use App\Services\Ai\ToolContext;
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
