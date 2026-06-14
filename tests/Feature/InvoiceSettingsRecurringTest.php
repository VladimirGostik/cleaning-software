<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\Invoices\InvoiceSettingsData;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InvoiceSettingsRecurringTest extends TestCase
{
    use RefreshDatabase;

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'invoice_template' => InvoiceTemplateEnum::Classic->value,
            'invoice_number_format' => 'FA-{YYYY}-{XXXX}',
            'iban' => null,
            'vat_rate' => null,
            'registration_info' => null,
            'recurring_default_state' => RecurringDefaultStateEnum::Draft->value,
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_recurring_default_state_draft_persists(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->put(route('settings.invoicing.update'), $this->basePayload([
            'recurring_default_state' => RecurringDefaultStateEnum::Draft->value,
        ]));

        $tenant->refresh();
        $this->assertSame(RecurringDefaultStateEnum::Draft, $tenant->interface->recurring_default_state);
    }

    public function test_recurring_default_state_issued_persists(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->put(route('settings.invoicing.update'), $this->basePayload([
            'recurring_default_state' => RecurringDefaultStateEnum::Issued->value,
        ]));

        $tenant->refresh();
        $this->assertSame(RecurringDefaultStateEnum::Issued, $tenant->interface->recurring_default_state);
    }

    public function test_settings_page_returns_recurring_state_options(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('settings.invoicing'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('recurringStateOptions'));
    }

    public function test_recurring_default_state_round_trips_via_dto(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->put(route('settings.invoicing.update'), $this->basePayload([
            'recurring_default_state' => RecurringDefaultStateEnum::Issued->value,
        ]));

        $tenant->refresh();
        $tenant->load('interface');

        $dto = InvoiceSettingsData::fromTenant($tenant);
        $this->assertSame(RecurringDefaultStateEnum::Issued, $dto->recurring_default_state);
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_invalid_recurring_default_state_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->put(route('settings.invoicing.update'), $this->basePayload([
            'recurring_default_state' => 'invalid_state',
        ]));

        $response->assertSessionHasErrors('recurring_default_state');
    }

    public function test_missing_recurring_default_state_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $payload = $this->basePayload();
        unset($payload['recurring_default_state']);

        $response = $this->put(route('settings.invoicing.update'), $payload);

        $response->assertSessionHasErrors('recurring_default_state');
    }
}
