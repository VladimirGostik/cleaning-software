<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Jobs\GenerateRecurringInvoiceJob;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class GenerateRecurringInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(array $attrs): RecurringInvoice
    {
        $tenantId = app('current_tenant_id');

        $ri = RecurringInvoice::factory()->create(array_merge([
            'tenant_id' => $tenantId,
            'frequency' => RecurringFrequencyEnum::Monthly,
            'type' => InvoiceTypeEnum::Monthly,
            'day_of_month' => 15,
            'customer_name' => 'Test Client',
            'due_days' => 14,
        ], $attrs));

        RecurringInvoiceItem::factory()->create([
            'tenant_id' => $tenantId,
            'recurring_invoice_id' => $ri->id,
        ]);

        return $ri;
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_dispatches_job_for_due_active_templates(): void
    {
        Queue::fake();

        $user = $this->actingAsTenantUser('Admin');

        $due = $this->makeTemplate([
            'status' => RecurringInvoiceStatusEnum::Active,
            'next_run_at' => now()->toDateString(),
        ]);

        $this->artisan('app:generate-recurring-invoices')->assertSuccessful();

        Queue::assertPushed(GenerateRecurringInvoiceJob::class, fn ($job) => $job->recurringInvoiceId === $due->id);
    }

    // -------------------------------------------------------------------------
    // Skipped statuses / conditions
    // -------------------------------------------------------------------------

    public function test_skips_paused_templates(): void
    {
        Queue::fake();

        $user = $this->actingAsTenantUser('Admin');

        $this->makeTemplate([
            'status' => RecurringInvoiceStatusEnum::Paused,
            'next_run_at' => now()->toDateString(),
        ]);

        $this->artisan('app:generate-recurring-invoices')->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_skips_future_next_run_at(): void
    {
        Queue::fake();

        $user = $this->actingAsTenantUser('Admin');

        $this->makeTemplate([
            'status' => RecurringInvoiceStatusEnum::Active,
            'next_run_at' => now()->addDays(5)->toDateString(),
        ]);

        $this->artisan('app:generate-recurring-invoices')->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_skips_null_next_run_at(): void
    {
        Queue::fake();

        $user = $this->actingAsTenantUser('Admin');

        $this->makeTemplate([
            'status' => RecurringInvoiceStatusEnum::Active,
            'next_run_at' => null,
        ]);

        $this->artisan('app:generate-recurring-invoices')->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_skips_completed_templates(): void
    {
        Queue::fake();

        $user = $this->actingAsTenantUser('Admin');

        $this->makeTemplate([
            'status' => RecurringInvoiceStatusEnum::Completed,
            'next_run_at' => now()->toDateString(),
        ]);

        $this->artisan('app:generate-recurring-invoices')->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_skips_cancelled_templates(): void
    {
        Queue::fake();

        $user = $this->actingAsTenantUser('Admin');

        $this->makeTemplate([
            'status' => RecurringInvoiceStatusEnum::Cancelled,
            'next_run_at' => now()->toDateString(),
        ]);

        $this->artisan('app:generate-recurring-invoices')->assertSuccessful();
        Queue::assertNothingPushed();
    }
}
