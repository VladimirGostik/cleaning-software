<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\Invoices\InvoiceSettingsData;
use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InvoiceSettingsDefaultsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'invoice_template' => InvoiceTemplateEnum::Classic->value,
            'invoice_number_format' => 'FA-{YYYY}-{XXXX}',
            'iban' => null,
            'vat_rate' => null,
            'registration_info' => null,
            'recurring_default_state' => 'draft',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_swift_bic_saved_on_tenant(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->put(route('settings.invoicing.update'), $this->basePayload([
            'swift_bic' => 'TATRSKBX',
        ]));

        $tenant->refresh();
        $this->assertSame('TATRSKBX', $tenant->swift_bic);
    }

    public function test_default_payment_type_persisted_on_interface(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->put(route('settings.invoicing.update'), $this->basePayload([
            'default_payment_type' => PaymentTypeEnum::Cash->value,
        ]));

        $tenant->refresh();
        $this->assertSame(PaymentTypeEnum::Cash, $tenant->interface->default_payment_type);
    }

    public function test_default_currency_persisted_on_interface(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->put(route('settings.invoicing.update'), $this->basePayload([
            'default_currency' => CurrencyEnum::CZK->value,
        ]));

        $tenant->refresh();
        $this->assertSame(CurrencyEnum::CZK, $tenant->interface->default_currency);
    }

    public function test_default_rounding_mode_persisted_on_interface(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->put(route('settings.invoicing.update'), $this->basePayload([
            'default_rounding_mode' => RoundingModeEnum::Document->value,
        ]));

        $tenant->refresh();
        $this->assertSame(RoundingModeEnum::Document, $tenant->interface->default_rounding_mode);
    }

    public function test_defaults_round_trip_via_dto(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $this->put(route('settings.invoicing.update'), $this->basePayload([
            'swift_bic' => 'GIBASKBX',
            'default_payment_type' => PaymentTypeEnum::Card->value,
            'default_currency' => CurrencyEnum::CZK->value,
            'default_rounding_mode' => RoundingModeEnum::Cash005->value,
            'default_constant_symbol' => '0558',
        ]));

        $tenant->refresh();
        $tenant->load('interface');
        $dto = InvoiceSettingsData::fromTenant($tenant);

        $this->assertSame('GIBASKBX', $dto->swift_bic);
        $this->assertSame(PaymentTypeEnum::Card, $dto->default_payment_type);
        $this->assertSame(CurrencyEnum::CZK, $dto->default_currency);
        $this->assertSame(RoundingModeEnum::Cash005, $dto->default_rounding_mode);
        $this->assertSame('0558', $dto->default_constant_symbol);
    }

    public function test_settings_page_has_new_enum_option_props(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->get(route('settings.invoicing'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Invoicing')
            ->has('paymentTypeOptions')
            ->has('currencyOptions')
            ->has('roundingModeOptions'),
        );
    }

    public function test_invoice_create_page_reflects_interface_defaults(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        // Save defaults via settings
        $this->put(route('settings.invoicing.update'), $this->basePayload([
            'default_payment_type' => PaymentTypeEnum::Cash->value,
            'default_constant_symbol' => '0308',
        ]));

        $response = $this->get(route('invoices.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Create')
            ->where('invoiceDefaults.payment_type', PaymentTypeEnum::Cash->value)
            ->where('invoiceDefaults.constant_symbol', '0308'),
        );
    }

    // -------------------------------------------------------------------------
    // Failure paths — validation
    // -------------------------------------------------------------------------

    public function test_invalid_swift_bic_format_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->put(route('settings.invoicing.update'), $this->basePayload([
            'swift_bic' => 'not-valid',
        ]));

        $response->assertSessionHasErrors('swift_bic');
    }

    public function test_swift_bic_too_long_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->put(route('settings.invoicing.update'), $this->basePayload([
            'swift_bic' => 'TATRSKBXXXXX', // 12 chars — exceeds max 11
        ]));

        $response->assertSessionHasErrors('swift_bic');
    }

    public function test_invalid_default_payment_type_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->put(route('settings.invoicing.update'), $this->basePayload([
            'default_payment_type' => 'wire',
        ]));

        $response->assertSessionHasErrors('default_payment_type');
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function test_null_swift_bic_and_constant_symbol_accepted(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->put(route('settings.invoicing.update'), $this->basePayload([
            'swift_bic' => null,
            'default_constant_symbol' => null,
        ]));

        $response->assertRedirect(route('invoices.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_missing_new_fields_falls_back_to_defaults(): void
    {
        // Old-style payload without new fields — should succeed with defaults
        $user = $this->actingAsTenantUser('Vlastník');
        $this->setUserPlan($user, SubscriptionPlanEnum::Pro);

        $response = $this->put(route('settings.invoicing.update'), $this->basePayload());

        $response->assertRedirect(route('invoices.index'));
        $response->assertSessionHasNoErrors();
    }
}
