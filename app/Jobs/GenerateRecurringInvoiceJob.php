<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\Invoices\InvoiceIssueData;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Services\InvoiceService;
use App\Services\RecurringInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(85)]
#[FailOnTimeout]
final class GenerateRecurringInvoiceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $recurringInvoiceId,
    ) {}

    public function uniqueId(): string
    {
        return $this->recurringInvoiceId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(
        RecurringInvoiceService $service,
        InvoiceService $invoiceService,
        DatabaseManager $db,
    ): void {
        /** @var RecurringInvoice $ri */
        $ri = RecurringInvoice::withoutGlobalScope(TenantScope::class)
            ->findOrFail($this->recurringInvoiceId);

        // Idempotency guard — state may have changed since dispatch
        $runAt = $ri->next_run_at;

        if (! $ri->isRunnable() || $runAt === null || ! $runAt->lte(now()->startOfDay())) {
            return;
        }

        // Bind tenant context so TenantScope, DTO exists-rules and InvoiceService work
        app()->instance('current_tenant_id', $ri->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($ri->tenant_id);

        $db->transaction(function () use ($ri, $runAt, $service, $invoiceService): void {
            $invoice = $service->generateInvoiceFromTemplate($ri);

            if ($invoice === null) {
                Log::info('recurring_invoice.generate.skipped_existing_period', [
                    'recurring_invoice_id' => $ri->id,
                    'tenant_id' => $ri->tenant_id,
                ]);
            } else {
                $this->issueAndSend($ri, $invoice, $service, $invoiceService);
            }

            // Compute next_run_at from current next_run_at before refresh to avoid drift
            $candidateNext = $ri->frequency->nextRunDate($runAt, $ri->day_of_month);

            if ($invoice !== null) {
                $ri->increment('occurrences_generated');
            }

            $ri->refresh();

            if ($ri->hasReachedLimit() || $ri->hasReachedEndDate($candidateNext)) {
                $ri->status = RecurringInvoiceStatusEnum::Completed;
                $ri->next_run_at = null;
            } else {
                $ri->next_run_at = $candidateNext;
            }

            $ri->last_generated_at = now();
            $ri->save();
        });
    }

    private function issueAndSend(RecurringInvoice $ri, Invoice $invoice, RecurringInvoiceService $service, InvoiceService $invoiceService): void
    {
        $tenantDefault = $service->resolveTenantDefaultState($ri->tenant_id);
        $shouldAutoIssue = $ri->auto_issue || $tenantDefault === RecurringDefaultStateEnum::Issued;

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($ri->tenant_id);
        $missingSupplierFields = $tenant->missingSupplierFields();

        if ($shouldAutoIssue && $missingSupplierFields === []) {
            $invoiceService->issue($invoice, new InvoiceIssueData(number: null), null);

            if ($ri->auto_send) {
                if ($invoice->customer_email !== null) {
                    $invoiceService->send($invoice);
                } else {
                    Log::warning('recurring_invoice.auto_send.skipped_no_email', [
                        'recurring_invoice_id' => $ri->id,
                        'invoice_id' => $invoice->id,
                        'tenant_id' => $ri->tenant_id,
                    ]);
                }
            }
        } elseif ($shouldAutoIssue) {
            Log::warning('recurring_invoice.auto_issue.skipped_supplier_incomplete', [
                'recurring_invoice_id' => $ri->id,
                'tenant_id' => $ri->tenant_id,
                'missing' => $missingSupplierFields,
            ]);
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('recurring_invoice.generate.failed', [
            'recurring_invoice_id' => $this->recurringInvoiceId,
            'exception' => $e?->getMessage(),
            'trace' => $e?->getTraceAsString(),
        ]);
    }
}
