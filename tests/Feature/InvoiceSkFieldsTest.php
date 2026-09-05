<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InvoiceSkFieldsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => InvoiceTypeEnum::OneOff->value,
            'issue_date' => '2026-06-01',
            'delivery_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'customer_name' => 'Test s.r.o.',
            'payment_type' => PaymentTypeEnum::Transfer->value,
            'currency' => CurrencyEnum::EUR->value,
            'rounding_mode' => RoundingModeEnum::None->value,
            'deposit' => 0,
            'items' => [
                [
                    'description' => 'Upratovanie',
                    'quantity' => 1,
                    'unit' => 'ks',
                    'unit_price' => 100.00,
                    'discount_percent' => 0,
                    'vat_rate' => 0,
                ],
            ],
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Happy path — SK fields stored
    // -------------------------------------------------------------------------

    public function test_create_invoice_with_payment_type_and_symbols_persists_all_sk_fields(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('invoices.store'), $this->basePayload([
            'payment_type' => PaymentTypeEnum::Card->value,
            'currency' => CurrencyEnum::EUR->value,
            'rounding_mode' => RoundingModeEnum::None->value,
            'constant_symbol' => '0308',
            'specific_symbol' => '1234',
            'header_text' => 'Hlavička faktúry',
            'footer_text' => 'Päta faktúry',
        ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $tenant = Tenant::where('owner_id', $user->id)->first();
        /** @var Invoice $invoice */
        $invoice = Invoice::where('tenant_id', $tenant->id)->latest()->first();

        $this->assertSame(PaymentTypeEnum::Card, $invoice->payment_type);
        $this->assertSame(CurrencyEnum::EUR, $invoice->currency);
        $this->assertSame(RoundingModeEnum::None, $invoice->rounding_mode);
        $this->assertSame('0308', $invoice->constant_symbol);
        $this->assertSame('1234', $invoice->specific_symbol);
        $this->assertSame('Hlavička faktúry', $invoice->header_text);
        $this->assertSame('Päta faktúry', $invoice->footer_text);
    }

    public function test_rounding_mode_document_rounds_total_to_whole_euro(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('invoices.store'), $this->basePayload([
            'rounding_mode' => RoundingModeEnum::Document->value,
            'items' => [
                [
                    'description' => 'Položka',
                    'quantity' => 1,
                    'unit' => 'ks',
                    'unit_price' => 100.70,
                    'discount_percent' => 0,
                    'vat_rate' => 0,
                ],
            ],
        ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $tenant = Tenant::where('owner_id', $user->id)->first();
        /** @var Invoice $invoice */
        $invoice = Invoice::where('tenant_id', $tenant->id)->latest()->first();

        $this->assertSame(101.0, (float) $invoice->total);
        $this->assertSame(0.30, round((float) $invoice->rounding_amount, 2));
    }

    public function test_rounding_mode_cash005_rounds_to_five_cents(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('invoices.store'), $this->basePayload([
            'rounding_mode' => RoundingModeEnum::Cash005->value,
            'items' => [
                [
                    'description' => 'Položka',
                    'quantity' => 1,
                    'unit' => 'ks',
                    'unit_price' => 100.12,
                    'discount_percent' => 0,
                    'vat_rate' => 0,
                ],
            ],
        ]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $tenant = Tenant::where('owner_id', $user->id)->first();
        /** @var Invoice $invoice */
        $invoice = Invoice::where('tenant_id', $tenant->id)->latest()->first();

        // 100.12 rounds to nearest 0.05 → 100.10
        $this->assertSame(100.10, (float) $invoice->total);
    }

    public function test_rounding_mode_none_yields_zero_rounding_amount(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('invoices.store'), $this->basePayload([
            'rounding_mode' => RoundingModeEnum::None->value,
            'items' => [
                [
                    'description' => 'Položka',
                    'quantity' => 1,
                    'unit' => 'ks',
                    'unit_price' => 100.47,
                    'discount_percent' => 0,
                    'vat_rate' => 0,
                ],
            ],
        ]));

        $response->assertRedirect();

        $tenant = Tenant::where('owner_id', $user->id)->first();
        /** @var Invoice $invoice */
        $invoice = Invoice::where('tenant_id', $tenant->id)->latest()->first();

        $this->assertSame(100.47, (float) $invoice->total);
        $this->assertSame(0.0, (float) $invoice->rounding_amount);
    }

    public function test_cancel_copies_sk_fields_to_credit_note(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $tenant->id,
            'payment_type' => PaymentTypeEnum::Cash,
            'currency' => CurrencyEnum::EUR,
            'rounding_mode' => RoundingModeEnum::None,
            'constant_symbol' => '0308',
            'specific_symbol' => '5678',
            'header_text' => 'Horný text',
            'footer_text' => 'Dolný text',
            'rounding_amount' => '0.00',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'line_base' => 100.00,
            'line_vat' => 0,
            'line_total' => 100.00,
        ]);

        $this->post(route('invoices.cancel', $invoice));

        $credit = Invoice::withoutGlobalScopes()
            ->where('credited_invoice_id', $invoice->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($credit);
        $this->assertSame(PaymentTypeEnum::Cash, $credit->payment_type);
        $this->assertSame(CurrencyEnum::EUR, $credit->currency);
        $this->assertSame(RoundingModeEnum::None, $credit->rounding_mode);
        $this->assertSame('0308', $credit->constant_symbol);
        $this->assertSame('5678', $credit->specific_symbol);
        $this->assertSame('Horný text', $credit->header_text);
        $this->assertSame('Dolný text', $credit->footer_text);
    }

    public function test_duplicate_copies_sk_fields_to_new_draft(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'payment_type' => PaymentTypeEnum::Card,
            'currency' => CurrencyEnum::EUR,
            'rounding_mode' => RoundingModeEnum::Document,
            'constant_symbol' => '1234',
            'specific_symbol' => null,
            'header_text' => 'Header',
            'footer_text' => null,
        ]);

        $this->post(route('invoices.duplicate', $invoice));

        $copy = Invoice::where('tenant_id', $tenant->id)
            ->where('id', '!=', $invoice->id)
            ->where('status', InvoiceStatusEnum::Draft)
            ->latest()
            ->first();

        $this->assertNotNull($copy);
        $this->assertSame(PaymentTypeEnum::Card, $copy->payment_type);
        $this->assertSame(CurrencyEnum::EUR, $copy->currency);
        $this->assertSame(RoundingModeEnum::Document, $copy->rounding_mode);
        $this->assertSame('1234', $copy->constant_symbol);
        $this->assertNull($copy->specific_symbol);
        $this->assertSame('Header', $copy->header_text);
        $this->assertNull($copy->footer_text);
    }

    public function test_create_page_has_payment_type_currency_rounding_props(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->get(route('invoices.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Create')
            ->has('paymentTypeOptions')
            ->has('currencyOptions')
            ->has('roundingModeOptions')
            ->has('invoiceDefaults'),
        );
    }

    public function test_edit_page_has_payment_type_currency_rounding_props(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Edit')
            ->has('paymentTypeOptions')
            ->has('currencyOptions')
            ->has('roundingModeOptions')
            ->has('invoiceDefaults'),
        );
    }

    // -------------------------------------------------------------------------
    // Failure paths — validation
    // -------------------------------------------------------------------------

    public function test_constant_symbol_longer_than_10_chars_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('invoices.store'), $this->basePayload([
            'constant_symbol' => '12345678901',
        ]));

        $response->assertSessionHasErrors('constant_symbol');
    }

    public function test_constant_symbol_with_letters_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('invoices.store'), $this->basePayload([
            'constant_symbol' => 'ABCD',
        ]));

        $response->assertSessionHasErrors('constant_symbol');
    }

    public function test_invalid_currency_value_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('invoices.store'), $this->basePayload([
            'currency' => 'INVALID',
        ]));

        $response->assertSessionHasErrors('currency');
    }

    public function test_invalid_payment_type_fails_validation(): void
    {
        $user = $this->actingAsTenantUser('Admin');

        $response = $this->post(route('invoices.store'), $this->basePayload([
            'payment_type' => 'wire',
        ]));

        $response->assertSessionHasErrors('payment_type');
    }
}
