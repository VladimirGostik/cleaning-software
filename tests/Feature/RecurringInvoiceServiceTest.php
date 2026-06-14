<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\RecurringInvoices\RecurringInvoiceUpsertData;
use App\Enums\InvoiceTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Services\RecurringInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RecurringInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecurringInvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RecurringInvoiceService::class);
    }

    private function makeUpsertData(array $overrides = []): RecurringInvoiceUpsertData
    {
        return RecurringInvoiceUpsertData::from(array_merge([
            'client_id' => null,
            'cleaning_object_id' => null,
            'name' => 'Test Recurring',
            'type' => InvoiceTypeEnum::Monthly->value,
            'template' => null,
            'frequency' => RecurringFrequencyEnum::Monthly->value,
            'day_of_month' => 15,
            'auto_issue' => false,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => null,
            'occurrences_limit' => null,
            'due_days' => 14,
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'customer_name' => 'Acme s.r.o.',
            'customer_representative' => null,
            'customer_ico' => null,
            'customer_dic' => null,
            'customer_vat_number' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'customer_country' => null,
            'customer_email' => null,
            'note' => null,
            'items' => [
                [
                    'description' => 'Cleaning service',
                    'quantity' => 1.0,
                    'unit' => null,
                    'unit_price' => 100.0,
                ],
            ],
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_create_standalone_sets_active_and_next_run_at(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $data = $this->makeUpsertData([
            'start_date' => now()->subMonth()->toDateString(), // past = use frequency->nextRunDate
        ]);

        $ri = $this->service->create($data);

        $this->assertSame(RecurringInvoiceStatusEnum::Active, $ri->status);
        $this->assertNotNull($ri->next_run_at);
        $this->assertCount(1, $ri->items);
    }

    public function test_create_with_future_start_date_uses_start_date_as_next_run(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $futureStart = now()->addMonths(2)->startOfMonth();
        $data = $this->makeUpsertData([
            'start_date' => $futureStart->toDateString(),
            'day_of_month' => 15,
        ]);

        $ri = $this->service->create($data);

        // next_run_at should be start_date clamped to day_of_month within that month
        $this->assertNotNull($ri->next_run_at);
        $this->assertTrue($ri->next_run_at->isFuture() || $ri->next_run_at->isToday());
    }

    public function test_create_client_linked(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenantId = app('current_tenant_id');

        $client = Client::factory()->create(['tenant_id' => $tenantId]);

        $data = $this->makeUpsertData([
            'client_id' => $client->id,
            'customer_name' => null,
        ]);

        $ri = $this->service->create($data);

        $this->assertSame($client->id, $ri->client_id);
    }

    public function test_indefinite_template_is_active_with_no_end_no_limit(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $data = $this->makeUpsertData([
            'end_date' => null,
            'occurrences_limit' => null,
        ]);

        $ri = $this->service->create($data);

        $this->assertSame(RecurringInvoiceStatusEnum::Active, $ri->status);
        $this->assertNull($ri->end_date);
        $this->assertNull($ri->occurrences_limit);
        $this->assertNotNull($ri->next_run_at);
    }

    public function test_pause_active_template_sets_paused_and_nulls_next_run(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $ri = $this->service->create($this->makeUpsertData());
        $this->assertSame(RecurringInvoiceStatusEnum::Active, $ri->status);

        $this->service->pause($ri);

        $this->assertSame(RecurringInvoiceStatusEnum::Paused, $ri->status);
        $this->assertNull($ri->next_run_at);
    }

    public function test_resume_paused_template_sets_active_and_next_run(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $ri = $this->service->create($this->makeUpsertData());
        $this->service->pause($ri);
        $this->service->resume($ri);

        $ri->refresh();
        $this->assertSame(RecurringInvoiceStatusEnum::Active, $ri->status);
        $this->assertNotNull($ri->next_run_at);
    }

    public function test_cancel_active_sets_cancelled_and_nulls_next_run(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $ri = $this->service->create($this->makeUpsertData());
        $this->service->cancel($ri);

        $this->assertSame(RecurringInvoiceStatusEnum::Cancelled, $ri->status);
        $this->assertNull($ri->next_run_at);
    }

    public function test_cancel_paused_sets_cancelled(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $ri = $this->service->create($this->makeUpsertData());
        $this->service->pause($ri);
        $this->service->cancel($ri);

        $this->assertSame(RecurringInvoiceStatusEnum::Cancelled, $ri->status);
    }

    // -------------------------------------------------------------------------
    // computeInitialNextRunAt — start_date = today
    // -------------------------------------------------------------------------

    public function test_start_date_today_day_of_month_equals_today_sets_next_run_today(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $todayDay = (int) now()->day;

        $data = $this->makeUpsertData([
            'start_date' => now()->toDateString(),
            'day_of_month' => $todayDay,
        ]);

        $ri = $this->service->create($data);

        $this->assertNotNull($ri->next_run_at);
        $this->assertTrue($ri->next_run_at->isToday());
    }

    public function test_start_date_today_day_of_month_already_passed_this_month_sets_next_run_to_next_interval(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Ensure we pick a day_of_month strictly before today's day
        $today = now();
        if ($today->day <= 1) {
            // On the 1st there's no day before it — skip gracefully
            $this->markTestSkipped('Cannot pick a day_of_month < 1; run on day >= 2.');
        }

        $pastDay = $today->day - 1; // a day that has already passed this month

        $data = $this->makeUpsertData([
            'start_date' => $today->toDateString(),
            'day_of_month' => $pastDay,
        ]);

        $ri = $this->service->create($data);

        $this->assertNotNull($ri->next_run_at);
        $this->assertTrue($ri->next_run_at->isAfter($today->startOfDay()), 'next_run_at must be in the future when day_of_month already passed this month');
        $this->assertFalse($ri->next_run_at->isToday(), 'next_run_at must not be today when day_of_month already passed');
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_pause_when_not_active_throws_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $ri = $this->service->create($this->makeUpsertData());
        $this->service->pause($ri);

        $this->expectException(ValidationException::class);
        $this->service->pause($ri);
    }

    public function test_resume_when_not_paused_throws_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $ri = $this->service->create($this->makeUpsertData());

        $this->expectException(ValidationException::class);
        $this->service->resume($ri);
    }

    public function test_update_completed_template_throws_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $ri = $this->service->create($this->makeUpsertData());
        $ri->update(['status' => RecurringInvoiceStatusEnum::Completed]);

        $this->expectException(ValidationException::class);
        $this->service->update($ri, $this->makeUpsertData());
    }

    public function test_cancel_completed_template_throws_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $ri = $this->service->create($this->makeUpsertData());
        $ri->update(['status' => RecurringInvoiceStatusEnum::Completed]);

        $this->expectException(ValidationException::class);
        $this->service->cancel($ri);
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function test_resume_when_limit_already_reached_marks_completed(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $ri = $this->service->create($this->makeUpsertData(['occurrences_limit' => 3]));
        $this->service->pause($ri);

        // Simulate that limit was reached while paused
        $ri->update(['occurrences_generated' => 3]);
        $ri->refresh();

        $this->service->resume($ri);
        $ri->refresh();

        $this->assertSame(RecurringInvoiceStatusEnum::Completed, $ri->status);
        $this->assertNull($ri->next_run_at);
    }

    public function test_delete_soft_deletes_template(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $ri = $this->service->create($this->makeUpsertData());
        $id = $ri->id;

        $this->service->delete($ri);

        $this->assertSoftDeleted('recurring_invoices', ['id' => $id]);
    }

    public function test_object_must_belong_to_client_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenantId = app('current_tenant_id');

        $clientA = Client::factory()->create(['tenant_id' => $tenantId]);
        $clientB = Client::factory()->create(['tenant_id' => $tenantId]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenantId, 'client_id' => $clientB->id]);

        // Attempt to assign an object belonging to clientB when clientA is selected
        $response = $this->post(route('recurring-invoices.store'), [
            'name' => 'Test',
            'client_id' => $clientA->id,
            'cleaning_object_id' => $objectB->id,
            'type' => InvoiceTypeEnum::Monthly->value,
            'frequency' => RecurringFrequencyEnum::Monthly->value,
            'day_of_month' => 15,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'due_days' => 14,
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'items' => [['description' => 'Test', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $response->assertSessionHasErrors('cleaning_object_id');
    }

    public function test_both_end_date_and_limit_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->post(route('recurring-invoices.store'), [
            'name' => 'Test',
            'type' => InvoiceTypeEnum::Monthly->value,
            'frequency' => RecurringFrequencyEnum::Monthly->value,
            'day_of_month' => 15,
            'auto_issue' => false,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'occurrences_limit' => 12,
            'due_days' => 14,
            'customer_name' => 'Acme',
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->endOfMonth()->toDateString(),
            'items' => [['description' => 'Test', 'quantity' => 1, 'unit_price' => 100]],
        ]);

        $response->assertSessionHasErrors('end_date');
    }
}
