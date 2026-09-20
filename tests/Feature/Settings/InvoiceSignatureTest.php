<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

final class InvoiceSignatureTest extends TestCase
{
    use RefreshDatabase;

    private string $signatureDisk = 'local';

    protected function setUp(): void
    {
        parent::setUp();
        $disk = config('invoicing.signature.disk');
        $this->signatureDisk = is_string($disk) ? $disk : 'local';
        Storage::fake('public');
        Storage::fake($this->signatureDisk);
    }

    /**
     * Real PNG magic bytes (padded to `$kb`) — Spatie sniffs actual file content, not the declared mime.
     * `$fill` pads the tail — pass a distinct character to make two generated signatures byte-distinguishable.
     */
    private function fakePng(string $name = 'signature.png', int $kb = 1, string $fill = '0'): UploadedFile
    {
        // Keep the source fake file referenced — its `tmpfile()` resource is deleted once GC'd.
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
            'name' => 'Signature Test s.r.o.',
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
            'invoice_template' => 'classic',
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

    // -------------------------------------------------------------------------
    // happy
    // -------------------------------------------------------------------------

    public function test_upload_signature_persists_media_on_private_disk(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());

        $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $uuid]))
            ->assertSessionDoesntHaveErrors();

        $tenant->refresh();
        $this->assertNotNull($tenant->signature_media_id);

        $media = $tenant->signatureMedia;
        $this->assertNotNull($media);
        $this->assertSame($this->signatureDisk, $media->disk);
        $this->assertSame($tenant->id, $media->tenant_id);
        $this->assertDatabaseCount('temporary_uploads', 0);
    }

    // -------------------------------------------------------------------------
    // failure
    // -------------------------------------------------------------------------

    public function test_upload_forbidden_without_manage_billing_settings(): void
    {
        // #[Authorize] on the PUT runs before DTO validation — an actor lacking
        // ManageBillingSettings never even needs a genuine staged upload to be blocked.
        // (The cleaner role also lacks UploadFiles, so routing this through the real
        // `/uploads` endpoint would 403 there first and mask the assertion under test.)
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => 'not-a-real-uuid']))
            ->assertForbidden();
    }

    public function test_signature_uuid_belonging_to_another_user_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());

        $stranger = User::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant, $stranger);

        $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $uuid]))
            ->assertSessionHasErrors('signature_uuid');
    }

    public function test_wrong_mime_type_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid(UploadedFile::fake()->create('signature.pdf', 1, 'application/pdf'));

        $response = $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $uuid]));
        $response->assertSessionHasErrors('signature_uuid');

        /** @var ViewErrorBag $errors */
        $errors = session('errors');
        $message = $errors->getBag('default')->first('signature_uuid');

        $this->assertStringContainsString(__('app.invoice_signature_invalid_type'), $message);
        $this->assertStringNotContainsString('app.invoice_signature_invalid_type', $message);
    }

    public function test_oversized_signature_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        config(['invoicing.signature.max_size_kb' => 100]);
        $uuid = $this->uploadAndGetUuid($this->fakePng('huge.png', 200));

        $response = $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $uuid]));
        $response->assertSessionHasErrors('signature_uuid');

        /** @var ViewErrorBag $errors */
        $errors = session('errors');
        $message = $errors->getBag('default')->first('signature_uuid');

        $this->assertStringContainsString(__('app.invoice_signature_too_large'), $message);
        $this->assertStringNotContainsString('app.invoice_signature_too_large', $message);
    }

    public function test_signature_uuid_with_remove_signature_together_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());

        $this->put(route('settings.invoicing.update'), $this->payload([
            'signature_uuid' => $uuid,
            'remove_signature' => true,
        ]))->assertSessionHasErrors('signature_uuid');
    }

    // -------------------------------------------------------------------------
    // edge
    // -------------------------------------------------------------------------

    public function test_unrelated_settings_save_leaves_existing_signature_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());
        $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $uuid]));
        $tenant->refresh();
        $signatureMediaId = $tenant->signature_media_id;

        $this->put(route('settings.invoicing.update'), $this->payload(['name' => 'Renamed s.r.o.']));

        $tenant->refresh();
        $this->assertSame($signatureMediaId, $tenant->signature_media_id);
    }

    // -------------------------------------------------------------------------
    // replace
    // -------------------------------------------------------------------------

    public function test_replace_signature_repoints_media_and_keeps_previous_row(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $firstUuid = $this->uploadAndGetUuid($this->fakePng('first.png'));
        $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $firstUuid]));
        $tenant->refresh();
        $firstMediaId = $tenant->signature_media_id;

        $secondUuid = $this->uploadAndGetUuid($this->fakePng('second.png'));
        $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $secondUuid]))
            ->assertSessionDoesntHaveErrors();

        $tenant->refresh();
        $this->assertNotSame($firstMediaId, $tenant->signature_media_id);
        $this->assertCount(2, $tenant->getMedia('signature'));
    }

    public function test_replacing_twice_keeps_all_rows_pointing_at_newest(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        foreach (['one.png', 'two.png', 'three.png'] as $name) {
            $uuid = $this->uploadAndGetUuid($this->fakePng($name));
            $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $uuid]));
        }

        $tenant->refresh();
        $this->assertCount(3, $tenant->getMedia('signature'));
        $this->assertSame('three.png', $tenant->signatureMedia?->file_name);
    }

    // -------------------------------------------------------------------------
    // remove
    // -------------------------------------------------------------------------

    public function test_remove_signature_nulls_pointer_but_keeps_media_row(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());
        $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $uuid]));
        $tenant->refresh();
        $mediaId = $tenant->signature_media_id;
        $this->assertNotNull($mediaId);

        $this->put(route('settings.invoicing.update'), $this->payload(['remove_signature' => true]))
            ->assertSessionDoesntHaveErrors();

        $tenant->refresh();
        $this->assertNull($tenant->signature_media_id);
        $this->assertCount(1, $tenant->getMedia('signature'));
    }

    public function test_remove_signature_when_none_exists_is_a_noop(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->put(route('settings.invoicing.update'), $this->payload(['remove_signature' => true]))
            ->assertSessionDoesntHaveErrors();

        $tenant->refresh();
        $this->assertNull($tenant->signature_media_id);
    }

    // -------------------------------------------------------------------------
    // stream (GET /settings/invoicing/signature)
    // -------------------------------------------------------------------------

    public function test_stream_returns_200_with_stored_mime_type(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());
        $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $uuid]))
            ->assertSessionDoesntHaveErrors();
        $tenant->refresh();
        $media = $tenant->signatureMedia;
        $this->assertNotNull($media);

        $response = $this->get(route('settings.invoicing.signature'));

        $response->assertOk();
        $response->assertHeader('Content-Type', $media->mime_type);
    }

    public function test_stream_returns_404_when_no_signature_stored(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $this->get(route('settings.invoicing.signature'))->assertNotFound();
    }

    public function test_stream_returns_404_when_file_missing_from_disk(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePng());
        $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $uuid]))
            ->assertSessionDoesntHaveErrors();
        $tenant->refresh();
        $media = $tenant->signatureMedia;
        $this->assertNotNull($media);
        Storage::disk($media->disk)->delete($media->getPathRelativeToRoot());

        $this->get(route('settings.invoicing.signature'))->assertNotFound();
    }

    public function test_stream_forbidden_without_manage_billing_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->get(route('settings.invoicing.signature'))->assertForbidden();
    }

    /**
     * Route takes no identifier — it resolves `Tenant::findOrFail(current_tenant_id())` off the
     * session's active tenant. Same user is a member of both tenants; each tenant gets its own
     * byte-distinguishable signature so a resolution bug (e.g. leaking the wrong tenant's media)
     * would fail this assertion even though the route shape never changes.
     */
    public function test_stream_never_returns_another_tenants_signature(): void
    {
        $tenantA = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenantA);
        $uuidA = $this->uploadAndGetUuid($this->fakePng('a.png', 1, 'A'));
        $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $uuidA]))
            ->assertSessionDoesntHaveErrors();
        $tenantA->refresh();
        $mediaA = $tenantA->signatureMedia;
        $this->assertNotNull($mediaA);
        $contentsA = Storage::disk($this->signatureDisk)->get($mediaA->getPathRelativeToRoot());

        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantB, $user);
        $uuidB = $this->uploadAndGetUuid($this->fakePng('b.png', 1, 'B'));
        $this->put(route('settings.invoicing.update'), $this->payload(['signature_uuid' => $uuidB]))
            ->assertSessionDoesntHaveErrors();
        $tenantB->refresh();
        $mediaB = $tenantB->signatureMedia;
        $this->assertNotNull($mediaB);
        $contentsB = Storage::disk($this->signatureDisk)->get($mediaB->getPathRelativeToRoot());

        // active tenant is now B (last `actingAsTenantUser` call wins the session binding)
        $response = $this->get(route('settings.invoicing.signature'));

        $response->assertOk();
        $this->assertSame($contentsB, $response->getContent());
        $this->assertNotSame($contentsA, $response->getContent());
    }
}
