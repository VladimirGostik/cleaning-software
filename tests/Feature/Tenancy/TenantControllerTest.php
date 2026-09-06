<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RoundingModeEnum;
use App\Enums\TenantColorEnum;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    private function withActiveMembership(User $user): Tenant
    {
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantInterface::factory()->create(['tenant_id' => $tenant->id, 'color' => '#2563EB']);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);
        $this->bindTenant($tenant);

        return $tenant;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fullPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Full Co',
            'ico' => '87654321',
            'dic' => '2012345678',
            'vat_number' => 'SK2012345678',
            'is_vat_payer' => true,
            'address_line' => 'Hlavná 1',
            'city' => 'Bratislava',
            'postal_code' => '811 01',
            'country' => 'SK',
            'contact_email' => 'fakturacia@demo.sk',
            'contact_phone' => '+421900000000',
            'iban' => 'SK8975000000000123456789',
            'swift_bic' => 'TATRSKBX',
        ], $overrides);
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_creates_tenant_and_switches_active_session(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);

        $response = $this->actingAs($user)->post('/tenants', [
            'name' => 'New Co',
            'ico' => '87654321',
        ]);

        $response->assertRedirect(route('settings.invoicing'));
        $newTenant = Tenant::where('name', 'New Co')->firstOrFail();
        $this->assertSame($newTenant->id, session('active_tenant_id'));
        $this->assertSame($user->id, $newTenant->owner_id);
        $this->assertSame('SK', $newTenant->country);
        $this->assertNotSame([], $newTenant->missingSupplierFields());
    }

    public function test_store_with_full_payload_persists_supplier_profile_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);

        $response = $this->actingAs($user)->post('/tenants', $this->fullPayload());

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', __('app.tenant_created'));

        $newTenant = Tenant::where('name', 'Full Co')->firstOrFail();
        $this->assertSame('87654321', $newTenant->ico);
        $this->assertSame('2012345678', $newTenant->dic);
        $this->assertSame('SK2012345678', $newTenant->vat_number);
        $this->assertTrue($newTenant->is_vat_payer);
        $this->assertSame('Hlavná 1', $newTenant->address_line);
        $this->assertSame('Bratislava', $newTenant->city);
        $this->assertSame('811 01', $newTenant->postal_code);
        $this->assertSame('SK', $newTenant->country);
        $this->assertSame('fakturacia@demo.sk', $newTenant->contact_email);
        $this->assertSame('+421900000000', $newTenant->contact_phone);
        $this->assertSame('SK8975000000000123456789', $newTenant->iban);
        $this->assertSame('TATRSKBX', $newTenant->swift_bic);
        $this->assertSame([], $newTenant->missingSupplierFields());
    }

    public function test_store_with_stray_leader_email_sends_no_invitation(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->withActiveMembership($user);

        $this->actingAs($user)->post('/tenants', $this->fullPayload([
            'name' => 'No Invite Co',
            'leader_email' => 'leader@example.com',
        ]));

        Notification::assertNothingSent();
        $this->assertDatabaseCount('tenant_invitations', 0);
    }

    public function test_store_with_copy_settings_copies_color_from_active_tenant(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);

        $this->actingAs($user)->post('/tenants', [
            'name' => 'Copy Co',
            'ico' => '11112222',
            'copy_settings' => true,
        ]);

        $newTenant = Tenant::where('name', 'Copy Co')->firstOrFail();
        $this->assertSame('#2563EB', $newTenant->interface->color->value);
    }

    public function test_store_with_copy_settings_and_no_source_color_is_null(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantInterface::factory()->create(['tenant_id' => $tenant->id, 'color' => null]);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);
        $this->bindTenant($tenant);

        $this->actingAs($user)->post('/tenants', [
            'name' => 'No Color Co',
            'ico' => '33334444',
            'copy_settings' => true,
        ]);

        $newTenant = Tenant::where('name', 'No Color Co')->firstOrFail();
        $this->assertNull($newTenant->interface->color);
    }

    public function test_store_with_copy_settings_copies_invoice_defaults_but_keeps_posted_identity(): void
    {
        $user = User::factory()->create();
        $source = $this->withActiveMembership($user);
        $source->update(['invoice_number_format' => 'SRC-{YYYY}-{XXXX}', 'vat_rate' => 23.5]);
        $sourceInterface = $source->interface;
        $this->assertNotNull($sourceInterface);
        $sourceInterface->update([
            'invoice_template' => InvoiceTemplateEnum::Modern,
            'recurring_default_state' => RecurringDefaultStateEnum::Issued,
            'default_constant_symbol' => '0308',
            'default_payment_type' => PaymentTypeEnum::Cash,
            'default_currency' => CurrencyEnum::EUR,
            'default_rounding_mode' => RoundingModeEnum::Document,
        ]);

        $this->actingAs($user)->post('/tenants', $this->fullPayload([
            'name' => 'Copy Full Co',
            'dic' => '9999999999',
            'iban' => 'SK1111000000000000000099',
            'color' => TenantColorEnum::Emerald600->value,
            'copy_settings' => true,
        ]));

        $newTenant = Tenant::where('name', 'Copy Full Co')->firstOrFail();
        $newInterface = $newTenant->interface;
        $this->assertNotNull($newInterface);
        $this->assertSame('SRC-{YYYY}-{XXXX}', $newTenant->invoice_number_format);
        $this->assertSame('23.50', $newTenant->vat_rate);
        $this->assertSame(InvoiceTemplateEnum::Modern, $newInterface->invoice_template);
        $this->assertSame(RecurringDefaultStateEnum::Issued, $newInterface->recurring_default_state);
        $this->assertSame('0308', $newInterface->default_constant_symbol);
        $this->assertSame(PaymentTypeEnum::Cash, $newInterface->default_payment_type);
        $this->assertSame(CurrencyEnum::EUR, $newInterface->default_currency);
        $this->assertSame(RoundingModeEnum::Document, $newInterface->default_rounding_mode);
        // Posted supplier identity is kept — never overwritten by source tenant.
        $this->assertSame('9999999999', $newTenant->dic);
        $this->assertSame('SK1111000000000000000099', $newTenant->iban);
        // Explicit color beats source color.
        $this->assertSame(TenantColorEnum::Emerald600, $newInterface->color);
    }

    public function test_store_is_unreachable_by_guest(): void
    {
        $response = $this->post('/tenants', ['name' => 'Guest Co', 'ico' => '99998888']);

        $response->assertRedirect(route('login'));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'invalid iban' => [['iban' => 'XX'], 'iban'];
        yield 'invalid swift' => [['swift_bic' => 'TATR'], 'swift_bic'];
        yield 'invalid country' => [['country' => 'SVK'], 'country'];
        yield 'invalid contact_email' => [['contact_email' => 'nope'], 'contact_email'];
        yield 'postal_code too long' => [['postal_code' => str_repeat('1', 17)], 'postal_code'];
        yield 'dic too long' => [['dic' => str_repeat('1', 21)], 'dic'];
        yield 'vat_number too long' => [['vat_number' => str_repeat('1', 21)], 'vat_number'];
        yield 'ico too long' => [['ico' => str_repeat('1', 21)], 'ico'];
        yield 'missing name' => [['name' => null], 'name'];
        yield 'missing ico' => [['ico' => null], 'ico'];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloadProvider')]
    public function test_store_with_invalid_payload_returns_422(array $overrides, string $invalidField): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);

        $payload = array_filter($this->fullPayload($overrides), fn ($value): bool => $value !== null);

        $response = $this->actingAs($user)->post('/tenants', $payload);

        $response->assertInvalid([$invalidField]);
    }

    // ── switch ────────────────────────────────────────────────────────────────

    public function test_switch_to_member_tenant_succeeds(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);
        $other = Tenant::factory()->forOwner($user)->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $other->id, 'is_active' => true, 'joined_at' => now()]);

        $response = $this->actingAs($user)->post("/tenants/{$other->id}/switch");

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($other->id, session('active_tenant_id'));
    }

    public function test_switch_to_non_member_tenant_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);
        $foreign = Tenant::factory()->create();

        $response = $this->actingAs($user)->post("/tenants/{$foreign->id}/switch");

        $response->assertForbidden();
    }

    public function test_switch_to_inactive_membership_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);
        $other = Tenant::factory()->forOwner($user)->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $other->id, 'is_active' => false, 'joined_at' => now()]);

        $response = $this->actingAs($user)->post("/tenants/{$other->id}/switch");

        $response->assertForbidden();
    }

    public function test_switch_to_inactive_tenant_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->withActiveMembership($user);
        $other = Tenant::factory()->forOwner($user)->create(['is_active' => false]);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $other->id, 'is_active' => true, 'joined_at' => now()]);

        $response = $this->actingAs($user)->post("/tenants/{$other->id}/switch");

        $response->assertForbidden();
    }
}
