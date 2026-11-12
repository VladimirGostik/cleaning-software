<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Data\Tenants\AddTenantData;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Services\RegistrationService;
use Database\Seeders\LegacyImportSeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class LegacyImportSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = Encrypter::generateKey('aes-256-gcm');
        $this->path = tempnam(sys_get_temp_dir(), 'legacy-seeder-').'.enc';

        config([
            'legacy_import.key' => base64_encode($this->key),
            'legacy_import.path' => $this->path,
            'legacy_import.dry_run' => false,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $firm = fn (int $id, string $name, string $ico): array => [
            'id' => $id, 'company_name' => $name, 'company_address' => 'Invented Street 1', 'postal_code' => '81101',
            'city' => 'Invented City', 'company_ico' => $ico, 'company_dic' => null, 'company_iban' => null, 'register' => null,
        ];
        $client = fn (int $id, string $name): array => [
            'id' => $id, 'company_name' => $name, 'company_address' => 'Client Street 1', 'postal_code' => '81102',
            'city' => 'Client City', 'company_ico' => null, 'company_dic' => null, 'company_iban' => null, 'register' => null,
        ];

        return [
            'firms' => [$firm(2, 'Invented Alpha', '11111110'), $firm(3, 'Invented Beta', '33333330')],
            'client_ic_dph' => [],
            'clients' => [$client(1, 'Invented Client')],
            'firm_clients_2026' => [['firm' => 2, 'client' => 1, 'invoices' => 1, 'last' => '2026-01-01']],
            'monthly_templates' => [[
                'id' => 1, 'firm' => 2, 'client' => 1, 'name' => 'Invented Street č.1',
                'header1' => null, 'header2' => null, 'header3' => null, 'header4' => null,
                'above' => '', 'services' => '', 'items' => [],
            ]],
        ];
    }

    /** @param  array<string, mixed>|null  $payload */
    private function encrypt(?array $payload = null, ?string $key = null): void
    {
        $encrypter = new Encrypter($key ?? $this->key, 'aes-256-gcm');

        file_put_contents($this->path, $encrypter->encryptString((string) json_encode($payload ?? $this->payload(), JSON_THROW_ON_ERROR)));
    }

    private function existingFirm(): Tenant
    {
        app(RegistrationService::class)->createOwner('Owner', 'owner@example.com', 'password123', 'Invented Alpha', '11111110');

        $tenant = Tenant::query()->where('ico', '11111110')->firstOrFail();
        TenantInterface::query()->where('tenant_id', $tenant->id)->update(['default_footer_text' => 'Invented footer']);

        return $tenant;
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'tenants' => Tenant::query()->count(),
            'clients' => Client::withoutGlobalScopes()->withTrashed()->count(),
            'objects' => CleaningObject::withoutGlobalScopes()->withTrashed()->count(),
        ];
    }

    public function test_imports_for_existing_firm_owner_and_copies_settings(): void
    {
        $this->existingFirm();
        $this->encrypt();

        $this->seed(LegacyImportSeeder::class);

        $this->assertSame(['tenants' => 2, 'clients' => 1, 'objects' => 1], $this->counts());

        $beta = Tenant::query()->where('ico', '33333330')->firstOrFail();
        $this->assertSame('Invented footer', TenantInterface::query()->where('tenant_id', $beta->id)->value('default_footer_text'));
        $this->assertTrue(Tenant::query()->where('ico', '11111110')->firstOrFail()->owner?->hasActiveMembership($beta->id));
    }

    public function test_settings_source_prefers_a_firm_with_a_signature(): void
    {
        $alpha = $this->existingFirm();
        TenantInterface::query()->where('tenant_id', $alpha->id)->update(['default_footer_text' => null]);
        $owner = $alpha->owner;
        $this->assertNotNull($owner);
        $beta = app(RegistrationService::class)->addTenant($owner, new AddTenantData(name: 'Invented Beta', ico: '33333330'));
        TenantInterface::query()->where('tenant_id', $beta->id)->update(['default_footer_text' => 'Beta footer']);
        app()->instance('current_tenant_id', $beta->id);
        $signature = $beta->addMediaFromString('sig-bytes')->usingFileName('sig.png')->toMediaCollection('signature');
        $beta->update(['signature_media_id' => $signature->id]);
        app()->forgetInstance('current_tenant_id');
        $this->encrypt();

        $this->seed(LegacyImportSeeder::class);

        $alpha->refresh();
        $this->assertNotNull($alpha->signature_media_id);
        $this->assertSame('Beta footer', TenantInterface::query()->where('tenant_id', $alpha->id)->value('default_footer_text'));
    }

    public function test_second_run_creates_nothing(): void
    {
        $this->existingFirm();
        $this->encrypt();

        $this->seed(LegacyImportSeeder::class);
        $after = $this->counts();
        $this->seed(LegacyImportSeeder::class);

        $this->assertSame($after, $this->counts());
    }

    public function test_prints_postal_code_counts(): void
    {
        $this->existingFirm();
        $this->encrypt([...$this->payload(), 'object_postal_codes' => ['invented street č.1' => '82107']]);

        $this->artisanCommand('db:seed', ['--class' => LegacyImportSeeder::class])
            ->expectsOutputToContain('Postal codes: 1 filled, 0 corrected, 0 invalid. Firms with auto-issue default: 0.')
            ->assertSuccessful();

        $this->assertSame('821 07', CleaningObject::withoutGlobalScopes()->firstOrFail()->postal_code);
    }

    public function test_no_existing_firm_throws_and_writes_nothing(): void
    {
        $this->encrypt();

        try {
            $this->seed(LegacyImportSeeder::class);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No imported firm exists yet', $e->getMessage());
        }

        $this->assertSame(['tenants' => 0, 'clients' => 0, 'objects' => 0], $this->counts());
    }

    public function test_wrong_key_throws_without_payload_text(): void
    {
        $this->existingFirm();
        $this->encrypt(key: Encrypter::generateKey('aes-256-gcm'));
        $before = $this->counts();

        try {
            $this->seed(LegacyImportSeeder::class);
            $this->fail('Expected RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('Invented', $e->getMessage());
            $this->assertStringNotContainsString('Invented', (string) $e->getPrevious()?->getMessage());
        }

        $this->assertSame($before, $this->counts());
    }

    public function test_missing_key_and_missing_file_throw(): void
    {
        $this->existingFirm();

        config(['legacy_import.key' => null]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('key is missing');
        $this->seed(LegacyImportSeeder::class);
    }

    public function test_missing_file_throws(): void
    {
        $this->existingFirm();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('file not found');
        $this->seed(LegacyImportSeeder::class);
    }

    public function test_dry_run_config_writes_nothing(): void
    {
        $this->existingFirm();
        $this->encrypt();
        config(['legacy_import.dry_run' => true]);
        $before = $this->counts();

        $this->seed(LegacyImportSeeder::class);

        $this->assertSame($before, $this->counts());
    }
}
