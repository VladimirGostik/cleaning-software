<?php

declare(strict_types=1);

namespace Tests\Feature\RecurringInvoices;

use App\Data\RecurringInvoices\RecurringInvoiceUpsertData;
use App\Enums\InvoiceTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Tenant;
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

    /** @param  array<string, mixed>  $overrides */
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
                ['description' => 'Cleaning service', 'quantity' => 1.0, 'unit' => null, 'unit_price' => 100.0, 'discount_percent' => 0, 'vat_rate' => 0],
            ],
            'constant_symbol' => null,
            'header_text' => null,
            'footer_text' => null,
            'deposit' => 0,
            'payment_type' => 'transfer',
            'currency' => 'EUR',
            'rounding_mode' => 'none',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_create_standalone_sets_active_and_next_run_at(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $ri = $this->service->create($this->makeUpsertData(['start_date' => now()->subMonth()->toDateString()]));

        $this->assertSame(RecurringInvoiceStatusEnum::Active, $ri->status);
        $this->assertNotNull($ri->next_run_at);
        $this->assertCount(1, $ri->items);
    }

    public function test_create_with_future_start_date_uses_start_date_as_next_run(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $futureStart = now()->addMonths(2)->startOfMonth();
        $ri = $this->service->create($this->makeUpsertData(['start_date' => $futureStart->toDateString(), 'day_of_month' => 15]));

        $this->assertNotNull($ri->next_run_at);
        $this->assertTrue($ri->next_run_at->isFuture() || $ri->next_run_at->isToday());
    }

    public function test_create_client_linked(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $ri = $this->service->create($this->makeUpsertData(['client_id' => $client->id, 'customer_name' => null]));

        $this->assertSame($client->id, $ri->client_id);
    }

    public function test_indefinite_template_is_active_with_no_end_no_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);

        $ri = $this->service->create($this->makeUpsertData(['end_date' => null, 'occurrences_limit' => null]));

        $this->assertSame(RecurringInvoiceStatusEnum::Active, $ri->status);
        $this->assertNull($ri->end_date);
        $this->assertNull($ri->occurrences_limit);
        $this->assertNotNull($ri->next_run_at);
    }

    public function test_pause_active_template_sets_paused_and_nulls_next_run(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = $this->service->create($this->makeUpsertData());

        $this->service->pause($ri);

        $this->assertSame(RecurringInvoiceStatusEnum::Paused, $ri->status);
        $this->assertNull($ri->next_run_at);
    }

    public function test_resume_paused_template_sets_active_and_next_run(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = $this->service->create($this->makeUpsertData());
        $this->service->pause($ri);

        $this->service->resume($ri);
        $ri->refresh();

        $this->assertSame(RecurringInvoiceStatusEnum::Active, $ri->status);
        $this->assertNotNull($ri->next_run_at);
    }

    public function test_cancel_active_sets_cancelled_and_nulls_next_run(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = $this->service->create($this->makeUpsertData());

        $this->service->cancel($ri);

        $this->assertSame(RecurringInvoiceStatusEnum::Cancelled, $ri->status);
        $this->assertNull($ri->next_run_at);
    }

    public function test_cancel_paused_sets_cancelled(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = $this->service->create($this->makeUpsertData());
        $this->service->pause($ri);

        $this->service->cancel($ri);

        $this->assertSame(RecurringInvoiceStatusEnum::Cancelled, $ri->status);
    }

    public function test_start_date_today_day_of_month_equals_today_sets_next_run_today(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $todayDay = (int) now()->day;

        $ri = $this->service->create($this->makeUpsertData(['start_date' => now()->toDateString(), 'day_of_month' => $todayDay]));

        $this->assertNotNull($ri->next_run_at);
        $this->assertTrue($ri->next_run_at->isToday());
    }

    // -------------------------------------------------------------------------
    // failure
    // -------------------------------------------------------------------------

    public function test_pause_when_not_active_throws_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = $this->service->create($this->makeUpsertData());
        $this->service->pause($ri);

        $this->expectException(ValidationException::class);

        $this->service->pause($ri);
    }

    public function test_resume_when_not_paused_throws_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = $this->service->create($this->makeUpsertData());

        $this->expectException(ValidationException::class);

        $this->service->resume($ri);
    }

    public function test_update_completed_template_throws_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = $this->service->create($this->makeUpsertData());
        $ri->update(['status' => RecurringInvoiceStatusEnum::Completed]);

        $this->expectException(ValidationException::class);

        $this->service->update($ri, $this->makeUpsertData());
    }

    public function test_cancel_completed_template_throws_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = $this->service->create($this->makeUpsertData());
        $ri->update(['status' => RecurringInvoiceStatusEnum::Completed]);

        $this->expectException(ValidationException::class);

        $this->service->cancel($ri);
    }

    public function test_object_must_belong_to_client_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $clientA = Client::factory()->create(['tenant_id' => $tenant->id]);
        $clientB = Client::factory()->create(['tenant_id' => $tenant->id]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $clientB->id]);

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
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

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

    // -------------------------------------------------------------------------
    // edge
    // -------------------------------------------------------------------------

    public function test_resume_when_limit_already_reached_marks_completed(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = $this->service->create($this->makeUpsertData(['occurrences_limit' => 3]));
        $this->service->pause($ri);
        $ri->update(['occurrences_generated' => 3]);
        $ri->refresh();

        $this->service->resume($ri);
        $ri->refresh();

        $this->assertSame(RecurringInvoiceStatusEnum::Completed, $ri->status);
        $this->assertNull($ri->next_run_at);
    }

    public function test_delete_soft_deletes_template(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $ri = $this->service->create($this->makeUpsertData());
        $id = $ri->id;

        $this->service->delete($ri);

        $this->assertSoftDeleted('recurring_invoices', ['id' => $id]);
    }
}
