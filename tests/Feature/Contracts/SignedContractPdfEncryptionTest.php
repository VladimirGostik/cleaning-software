<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Documents\SignedPdfStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
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

        $this->artisanCommand('app:encrypt-signed-pdfs')->expectsOutputToContain('Encrypted 0 plain PDF(s); 1 already encrypted')->assertSuccessful();
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
}
