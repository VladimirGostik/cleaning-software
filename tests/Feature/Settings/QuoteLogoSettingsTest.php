<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class QuoteLogoSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $disk = 'local';

    protected function setUp(): void
    {
        parent::setUp();
        $disk = config('invoicing.logo.disk');
        $this->disk = is_string($disk) ? $disk : 'local';
        Storage::fake('public');
        Storage::fake($this->disk);
    }

    private function fakePng(string $name = 'logo.png', int $kb = 1, string $fill = '0'): UploadedFile
    {
        $source = UploadedFile::fake()->image('tmp.png', 40, 40);
        $bytes = (string) file_get_contents($source->getRealPath());
        $padded = $bytes.str_repeat($fill, max(0, $kb * 1024 - strlen($bytes)));

        return UploadedFile::fake()->createWithContent($name, $padded);
    }

    private function uploadAndGetUuid(UploadedFile $file): string
    {
        /** @var string $uuid */
        $uuid = $this->postJson('/uploads', ['file' => $file])->json('uuid');

        return $uuid;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Logo Test s.r.o.',
            'ico' => '12345678',
            'dic' => '2012345678',
            'vat_number' => 'SK2012345678',
            'is_vat_payer' => true,
            'address_line' => 'Hlavná 1',
            'city' => 'Bratislava',
            'postal_code' => '811 01',
            'country' => 'SK',
            'contact_email' => 'billing@example.com',
            'contact_phone' => '+421900000000',
            'invoice_number_format' => 'FA-{YYYY}-{XXXX}',
            'iban' => 'SK8975000000000123456789',
            'vat_rate' => 23,
            'registration_info' => null,
            'recurring_default_state' => 'draft',
            'swift_bic' => 'TATRSKBX',
            'default_constant_symbol' => null,
            'signature_uuid' => null,
            'default_payment_type' => 'transfer',
            'default_currency' => 'EUR',
            'default_rounding_mode' => 'none',
            'remove_signature' => false,
        ], $overrides);
    }

    public function test_upload_logo_stores_it_on_the_private_disk_for_the_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());

        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $uuid]))
            ->assertSessionDoesntHaveErrors();

        $media = $tenant->refresh()->logoMedia();
        $this->assertNotNull($media);
        $this->assertSame($this->disk, $media->disk);
        $this->assertSame($tenant->id, $media->tenant_id);
        $this->assertNull($tenant->signatureMedia);
    }

    public function test_replacing_the_logo_keeps_a_single_file(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $first = $this->uploadAndGetUuid($this->fakePng('a.png', 1, 'a'));
        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $first]));
        $firstId = $tenant->refresh()->logoMedia()?->id;

        $second = $this->uploadAndGetUuid($this->fakePng('b.png', 1, 'b'));
        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $second]))
            ->assertSessionDoesntHaveErrors();

        $tenant->refresh();
        $this->assertCount(1, $tenant->getMedia('logo'));
        $this->assertNotSame($firstId, $tenant->logoMedia()?->id);
    }

    public function test_remove_logo_clears_the_collection(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());
        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $uuid]));

        $this->put(route('settings.invoicing.update'), $this->payload(['remove_logo' => true]))
            ->assertSessionDoesntHaveErrors();

        $this->assertNull($tenant->refresh()->logoMedia());
    }

    public function test_unrelated_save_keeps_the_logo(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());
        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $uuid]));

        $this->put(route('settings.invoicing.update'), $this->payload(['name' => 'Renamed s.r.o.']));

        $this->assertNotNull($tenant->refresh()->logoMedia());
    }

    public function test_svg_logo_is_rejected_with_translated_message(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid(UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'));

        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $uuid]))
            ->assertSessionHasErrors('logo_uuid');

        /** @var ViewErrorBag $errors */
        $errors = session('errors');
        $this->assertStringContainsString(__('app.quote_logo_invalid_type'), $errors->getBag('default')->first('logo_uuid'));
        $this->assertNull($tenant->refresh()->logoMedia());
    }

    public function test_oversized_logo_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        config(['invoicing.logo.max_size_kb' => 100]);
        $uuid = $this->uploadAndGetUuid($this->fakePng('huge.png', 200));

        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $uuid]))
            ->assertSessionHasErrors('logo_uuid');

        /** @var ViewErrorBag $errors */
        $errors = session('errors');
        $this->assertStringContainsString(__('app.quote_logo_too_large'), $errors->getBag('default')->first('logo_uuid'));
    }

    public function test_logo_uuid_together_with_remove_logo_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());

        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $uuid, 'remove_logo' => true]))
            ->assertSessionHasErrors('logo_uuid');
    }

    public function test_logo_uuid_of_another_user_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());

        $this->actingAsTenantUser('Admin', $tenant, User::factory()->create());

        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $uuid]))
            ->assertSessionHasErrors('logo_uuid');
    }

    public function test_update_forbidden_without_manage_billing_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => 'x']))->assertForbidden();
    }

    public function test_logo_route_streams_the_file_and_404s_without_one(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('settings.invoicing.logo'))->assertNotFound();

        $uuid = $this->uploadAndGetUuid($this->fakePng());
        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $uuid]));

        $this->get(route('settings.invoicing.logo'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_logo_route_forbidden_without_manage_billing_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->get(route('settings.invoicing.logo'))->assertForbidden();
    }

    public function test_logo_route_never_serves_another_tenants_logo(): void
    {
        $other = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $other);
        $uuid = $this->uploadAndGetUuid($this->fakePng());
        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $uuid]));

        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('settings.invoicing.logo'))->assertNotFound();
    }

    public function test_show_exposes_logo_constraints_and_logo(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('settings.invoicing'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('logo', null)
            ->where('logo_constraints.max_size_kb', 1024)
            ->has('logo_constraints.allowed_mimes', 3));

        $uuid = $this->uploadAndGetUuid($this->fakePng());
        $this->put(route('settings.invoicing.update'), $this->payload(['logo_uuid' => $uuid]));

        $this->get(route('settings.invoicing'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('logo.url', route('settings.invoicing.logo'))
            ->where('logo.mime_type', 'image/png'));
    }

    public function test_signing_place_and_default_included_text_are_saved_and_blank_becomes_null(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload([
            'signing_place' => 'Vo Zvolene',
            'default_quote_included_text' => "Materials\nTravel",
        ]))->assertSessionDoesntHaveErrors();

        $this->assertSame('Vo Zvolene', $tenant->refresh()->signing_place);
        $this->assertSame("Materials\nTravel", TenantInterface::query()->where('tenant_id', $tenant->id)->value('default_quote_included_text'));

        $this->put(route('settings.invoicing.update'), $this->payload(['signing_place' => '  ', 'default_quote_included_text' => '']));

        $this->assertNull($tenant->refresh()->signing_place);
        $this->assertNull(TenantInterface::query()->where('tenant_id', $tenant->id)->value('default_quote_included_text'));
    }

    public function test_signing_place_and_default_text_length_limits(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload([
            'signing_place' => str_repeat('a', 101),
            'default_quote_included_text' => str_repeat('a', 2001),
        ]))->assertSessionHasErrors(['signing_place', 'default_quote_included_text']);
    }
}
