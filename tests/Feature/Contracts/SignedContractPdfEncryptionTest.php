<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Exceptions\SignedPdfUnreadableException;
use App\Models\Contract;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Documents\SignedPdfStore;
use App\Services\Pdf\ContractPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\TestCase;

final class SignedContractPdfEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private const NATIONAL_ID = '900101/1234';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config()->string('documents.disk'));
        $this->tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $this->tenant);
    }

    private function signedContract(): Contract
    {
        $membership = TenantMembership::factory()->create(['tenant_id' => $this->tenant->id]);
        $contract = Contract::factory()->forMembership($membership)
            ->withBody('<p>RČ '.self::NATIONAL_ID.'</p>')
            ->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('contracts.sign', $contract))->assertSessionHasNoErrors();

        return $contract->fresh() ?? $contract;
    }

    private function media(Contract $contract): Media
    {
        /** @var Media $media */
        $media = $contract->document?->getFirstMedia(SignedPdfStore::COLLECTION);

        return $media;
    }

    private function raw(Media $media): string
    {
        return (string) Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
    }

    private function makeLegacyPlain(Media $media): void
    {
        Storage::disk($media->disk)->put($media->getPathRelativeToRoot(), '%PDF-legacy '.self::NATIONAL_ID);
    }

    public function test_stored_bytes_are_encrypted_and_hide_the_national_id(): void
    {
        $raw = $this->raw($this->media($this->signedContract()));

        $this->assertFalse(str_starts_with($raw, '%PDF'));
        $this->assertStringNotContainsString(self::NATIONAL_ID, $raw);
        $this->assertStringContainsString(self::NATIONAL_ID, Crypt::decryptString($raw));
    }

    public function test_authorised_download_returns_a_plain_pdf(): void
    {
        $contract = $this->signedContract();

        $response = $this->get(route('contracts.pdf', $contract))->assertOk();

        $response->assertHeader('Content-Type', 'application/pdf');
        $body = $response->getContent();
        $this->assertTrue(str_starts_with((string) $body, '%PDF'));
        $this->assertStringContainsString(self::NATIONAL_ID, (string) $body);
    }

    public function test_unauthorised_user_cannot_download(): void
    {
        $contract = $this->signedContract();
        $this->actingAsTenantUser('Interná upratovačka', $this->tenant);

        $this->get(route('contracts.pdf', $contract))->assertForbidden();
    }

    public function test_a_legacy_plain_snapshot_still_serves(): void
    {
        $contract = $this->signedContract();
        $this->makeLegacyPlain($this->media($contract));

        $response = $this->get(route('contracts.pdf', $contract))->assertOk();

        $this->assertSame('%PDF-legacy '.self::NATIONAL_ID, $response->getContent());
    }

    public function test_command_upgrades_plain_snapshots_once(): void
    {
        $contract = $this->signedContract();
        $media = $this->media($contract);
        $this->makeLegacyPlain($media);

        $this->artisanCommand('app:encrypt-signed-pdfs', ['--dry-run' => true])->expectsOutputToContain('Would encrypt 1')->assertSuccessful();
        $this->assertTrue(str_starts_with($this->raw($media), '%PDF'));

        $this->artisanCommand('app:encrypt-signed-pdfs')->expectsOutputToContain('Encrypted 1')->assertSuccessful();
        $raw = $this->raw($media);
        $this->assertFalse(str_starts_with($raw, '%PDF'));
        $this->assertSame('%PDF-legacy '.self::NATIONAL_ID, Crypt::decryptString($raw));
        $this->assertSame(strlen($raw), $media->fresh()?->size);

        $this->artisanCommand('app:encrypt-signed-pdfs')->expectsOutputToContain('Encrypted 0 plain PDF(s); 1 already encrypted; 0 metadata repaired')->assertSuccessful();
        $this->assertSame($raw, $this->raw($media));
        $this->get(route('contracts.pdf', $contract))->assertOk();
    }

    public function test_the_media_viewer_and_contract_page_do_not_expose_the_snapshot(): void
    {
        $contract = $this->signedContract();
        $media = $this->media($contract);

        $this->withoutVite()->get(route('media.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('media.data', []));
        $this->get(route('media.show', $media))->assertForbidden();

        $this->get(route('contracts.show', $contract))->assertOk()->assertInertia(function (AssertableInertia $page) use ($media): void {
            $this->assertStringNotContainsString($media->file_name, (string) json_encode($page->toArray()['props']));
        });
    }

    public function test_the_real_pdf_service_serves_a_stored_encrypted_and_a_stored_plain_snapshot(): void
    {
        $contract = $this->signedContract();
        $media = $this->media($contract);
        $service = app(ContractPdfService::class);

        $this->assertSame('%PDF-fake RČ '.self::NATIONAL_ID, strip_tags($service->render($contract->fresh() ?? $contract)));

        $this->makeLegacyPlain($media);

        $this->assertSame('%PDF-legacy '.self::NATIONAL_ID, $service->render($contract->fresh() ?? $contract));
    }

    public function test_an_unreadable_snapshot_gives_a_translated_error_and_a_log_without_content(): void
    {
        $contract = $this->signedContract();
        $media = $this->media($contract);
        Storage::disk($media->disk)->put($media->getPathRelativeToRoot(), 'garbage-not-encrypted-content');
        Log::shouldReceive('error')->once()->with('contract.signed_pdf.unreadable', Mockery::on(
            fn (array $context): bool => $context['media_id'] === $media->id && ! str_contains((string) json_encode($context), 'garbage'),
        ));

        $this->from(route('contracts.show', $contract))->get(route('contracts.pdf', $contract))
            ->assertRedirect(route('contracts.show', $contract))
            ->assertSessionHas('error', __('app.contract_signed_pdf_unreadable'));
    }

    public function test_an_empty_blob_is_unreadable(): void
    {
        $media = $this->media($this->signedContract());
        Storage::disk($media->disk)->put($media->getPathRelativeToRoot(), '');

        $this->expectException(SignedPdfUnreadableException::class);

        app(SignedPdfStore::class)->read($media);
    }

    public function test_a_rerun_repairs_metadata_left_stale_by_an_interrupted_upgrade(): void
    {
        $media = $this->media($this->signedContract());
        $media->forceFill(['size' => 1, 'mime_type' => 'application/pdf'])->save();

        $this->artisanCommand('app:encrypt-signed-pdfs')->expectsOutputToContain('1 metadata repaired')->assertSuccessful();

        $media->refresh();
        $this->assertSame(strlen($this->raw($media)), $media->size);
        $this->assertSame('text/plain', $media->mime_type);
        $this->artisanCommand('app:encrypt-signed-pdfs')->expectsOutputToContain('0 metadata repaired')->assertSuccessful();
    }
}
