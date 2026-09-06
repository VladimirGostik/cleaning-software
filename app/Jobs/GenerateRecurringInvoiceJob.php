<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\Invoices\InvoiceIssueData;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\RecurringInvoice;
use App\Scopes\TenantScope;
use App\Services\InvoiceService;
use App\Services\RecurringInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(120)]
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
        if (! $ri->isRunnable() || ! $ri->next_run_at?->lte(now()->startOfDay())) {
            return;
        }

        // Bind tenant context so TenantScope, DTO exists-rules and InvoiceService work
        app()->instance('current_tenant_id', $ri->tenant_id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($ri->tenant_id);

        $db->transaction(function () use ($ri, $service, $invoiceService): void {
            $invoice = $service->generateInvoiceFromTemplate($ri);

            $tenantDefault = $service->resolveTenantDefaultState($ri->tenant_id);
            $shouldAutoIssue = $ri->auto_issue || $tenantDefault === RecurringDefaultStateEnum::Issued;

            if ($shouldAutoIssue) {
                $invoiceService->issue($invoice, new InvoiceIssueData(number: null));
            }

            // Compute next_run_at from current next_run_at before refresh to avoid drift
            $candidateNext = $ri->frequency->nextRunDate($ri->next_run_at, $ri->day_of_month);

            $ri->increment('occurrences_generated');
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

    public function failed(?Throwable $e): void
    {
        Log::error('recurring_invoice.generate.failed', [
            'recurring_invoice_id' => $this->recurringInvoiceId,
            'exception' => $e?->getMessage(),
            'trace' => $e?->getTraceAsString(),
        ]);
    }
}
