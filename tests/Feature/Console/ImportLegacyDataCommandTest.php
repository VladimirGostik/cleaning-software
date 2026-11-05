<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Data\Tenants\TenantSupplierProfileData;
use App\Enums\ObjectTypeEnum;
use App\Enums\TenantColorEnum;
use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Media;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\RegistrationService;
use ArrayObject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDOException;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ImportLegacyDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private const string PATH = 'import/legacy-cleaning-system.json';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('s3');
    }

    /** @param  array<mixed>|null  $payload */
    private function writeFile(?array $payload = null, string $disk = 'local'): void
    {
        Storage::disk($disk)->put(self::PATH, (string) json_encode($payload ?? $this->fixture(), JSON_THROW_ON_ERROR));
    }

    private function owner(string $email = 'owner@example.com', string $ico = '99999999', ?TenantSupplierProfileData $supplier = null): User
    {
        return app(RegistrationService::class)->createOwner('Owner', $email, 'password123', 'Placeholder Firm '.$ico, $ico, $supplier);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{int, string}
     */
    private function importLegacy(array $options = []): array
    {
        $code = Artisan::call('app:import-legacy', ['--owner' => 'owner@example.com', ...$options]);

        return [$code, Artisan::output()];
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        return [
            'tenants' => Tenant::query()->count(),
            'clients' => Client::withoutGlobalScopes()->withTrashed()->count(),
            'objects' => CleaningObject::withoutGlobalScopes()->withTrashed()->count(),
            'activity' => Activity::query()->count(),
            'roles' => DB::table('roles')->count(),
            'model_has_roles' => DB::table('model_has_roles')->count(),
            'media' => DB::table('media')->count(),
            'interfaces' => TenantInterface::query()->count(),
        ];
    }

    private function interfaceOf(Tenant $tenant): TenantInterface
    {
        return TenantInterface::query()->where('tenant_id', $tenant->id)->firstOrFail();
    }

    private function tenant(string $ico): Tenant
    {
        return Tenant::query()->where('ico', $ico)->firstOrFail();
    }

    /** @return Collection<int, Client> */
    private function clientsOf(Tenant $tenant): Collection
    {
        return Client::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
    }

    private function objectNamed(string $name): CleaningObject
    {
        return CleaningObject::withoutGlobalScopes()->where('name', $name)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $firm = fn (int $id, string $name, string $ico, ?array $register): array => [
            'id' => $id, 'company_name' => $name, 'company_address' => 'Legacy Street 1', 'postal_code' => '81101',
            'city' => 'Legacy City', 'company_ico' => $ico, 'company_dic' => '20 20 20 '.$id, 'company_iban' => 'sk89 7500 0000 0001 2345 6789',
            'bank_connection' => 'Test Bank', 'register' => $register,
        ];
        $client = fn (int $id, string $name, ?string $ico, ?array $register = null): array => [
            'id' => $id, 'company_name' => $name, 'company_address' => 'Client Street 1', 'postal_code' => '81102',
            'city' => 'Client City', 'company_ico' => $ico, 'company_dic' => null, 'company_iban' => null, 'register' => $register,
        ];
        $template = fn (int $id, int $firm, int $client, string $name, array $headers = []): array => [
            'id' => $id, 'firm' => $firm, 'client' => $client, 'name' => $name,
            'header1' => null, 'header2' => null, 'header3' => null, 'header4' => null,
            'above' => '', 'services' => '', 'items' => [], ...$headers,
        ];

        return [
            'source' => 'invented',
            'firms' => [
                $firm(2, 'Legacy Alpha', '11 111 111', ['name' => 'Alpha Cleaning s.r.o.', 'ico' => '22 222 222', 'street' => 'Alpha Street 5', 'city' => 'Alpha City', 'postal_code' => '90201']),
                $firm(3, 'Beta Cleaning s.r.o.', '33333330', ['name' => 'Beta Cleaning s.r.o.', 'ico' => '33333330', 'street' => 'Beta Street 1', 'city' => 'Beta City', 'postal_code' => '81101']),
                $firm(23, 'Gamma Cleaning s.r.o.', '44 444 444', null),
                $firm(99, 'Ignored Firm', '55555550', null),
            ],
            'firm_ic_dph' => [2 => null, 3 => null, 23 => null],
            'clients' => [
                $client(1, 'Legacy Client One', '11 000 000', ['name' => 'Real Client One a.s.', 'ico' => '11000001', 'street' => 'Real Street 1', 'city' => 'Real City', 'postal_code' => '01001']),
                $client(2, 'Private Person', null),
                $client(3, 'Dup Client A', '33 000 000', ['name' => 'Dup Client a.s.', 'ico' => '33000001', 'street' => 'Dup Street 1', 'city' => 'Dup City', 'postal_code' => '04001']),
                $client(4, 'Dup Client B', '33 000 000', ['name' => 'Dup Client a.s.', 'ico' => '33000001', 'street' => 'Dup Street 1', 'city' => 'Dup City', 'postal_code' => '04001']),
            ],
            'client_ic_dph' => [1 => null, 2 => null, 3 => null, 4 => null],
            'firm_clients_2026' => [
                ['firm' => 2, 'client' => 2, 'invoices' => 3, 'last' => '2026-01-01'],
                ['firm' => 3, 'client' => 2, 'invoices' => 1, 'last' => '2026-01-01'],
                ['firm' => 23, 'client' => 1, 'invoices' => 1, 'last' => '2026-01-01'],
            ],
            'monthly_templates' => [
                $template(1, 2, 1, 'Test Street č.13,15', ['header1' => 'Spoločenstvo vlastníkov', 'header2' => 'bytov Test Street 13', 'header3' => '811 03 Bratislava-Test']),
                $template(2, 2, 1, 'Test Street č.13,15 kont.stojisko'),
                $template(3, 2, 1, 'Orphan Street č.5 kont. stojisko'),
                $template(4, 2, 2, 'Kancelária Test', ['header4' => 'Header Four s.r.o.']),
                $template(5, 3, 3, 'Beta Street č.7a'),
                $template(6, 3, 4, 'Beta Street č.1-3', ['header2' => '040 01 Košice']),
                $template(7, 99, 3, 'Ignored Object č.1'),
            ],
            'last_invoice_number_2026' => [],
            'register_source' => 'invented',
        ];
    }

    public function test_creates_firms_clients_and_objects(): void
    {
        $owner = $this->owner();
        $this->writeFile();

        [$code, $output] = $this->importLegacy();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Created: 3 firms, 5 clients, 5 objects, 0 recurring invoices, 0 drafts.', $output);
        $this->assertSame(4, Tenant::query()->count());
        $this->assertNull(Tenant::query()->where('ico', '55555550')->first());

        $alpha = $this->tenant('22222222');
        $this->assertSame('Alpha Cleaning s.r.o.', $alpha->name);
        $this->assertSame('Alpha Street 5', $alpha->address_line);
        $this->assertSame('Alpha City', $alpha->city);
        $this->assertSame('902 01', $alpha->postal_code);
        $this->assertSame('2020202', $alpha->dic);
        $this->assertSame('SK8975000000000123456789', $alpha->iban);
        $this->assertFalse($alpha->is_vat_payer);
        $this->assertNull($alpha->vat_number);
        $this->assertTrue($owner->hasActiveMembership($alpha->id));
        $this->assertSame('Gamma Cleaning s.r.o.', $this->tenant('44444444')->name);
    }

    public function test_owner_is_admin_member_of_created_firms(): void
    {
        $owner = $this->owner();
        $this->writeFile();
        $this->importLegacy();

        $tenant = $this->tenant('33333330');
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $owner->unsetRelation('roles');

        $this->assertTrue($owner->hasRole('Admin'));
    }

    public function test_clients_come_from_templates_union_2026_pairs(): void
    {
        $this->owner();
        $this->writeFile();
        $this->importLegacy();

        $alpha = $this->clientsOf($this->tenant('22222222'));
        $beta = $this->clientsOf($this->tenant('33333330'));
        $gamma = $this->clientsOf($this->tenant('44444444'));

        $this->assertCount(2, $alpha);
        $this->assertCount(2, $beta);
        $this->assertCount(1, $gamma);
        $this->assertSame(0, CleaningObject::withoutGlobalScopes()->where('client_id', $gamma->firstOrFail()->id)->count());
        $this->assertSame(0, CleaningObject::withoutGlobalScopes()->where('client_id', $beta->firstOrFail('name', 'Private Person')->id)->count());
    }

    public function test_two_legacy_clients_with_same_register_ico_make_one_client(): void
    {
        $this->owner();
        $this->writeFile();
        $this->importLegacy();

        $beta = $this->clientsOf($this->tenant('33333330'));

        $this->assertCount(1, $beta->where('ico', '33000001'));
        $client = $beta->firstOrFail('ico', '33000001');
        $this->assertSame(2, CleaningObject::withoutGlobalScopes()->where('client_id', $client->id)->count());
    }

    public function test_client_type_and_register_data(): void
    {
        $this->owner();
        $this->writeFile();
        $this->importLegacy();

        $alpha = $this->clientsOf($this->tenant('22222222'));
        $corporate = $alpha->firstOrFail('ico', '11000001');
        $private = $alpha->firstOrFail('name', 'Private Person');

        $this->assertSame('corporate', $corporate->type->value);
        $this->assertSame('Real Client One a.s.', $corporate->name);
        $this->assertSame('Real Street 1', $corporate->street);
        $this->assertSame('010 01', $corporate->postal_code);
        $this->assertSame('private', $private->type->value);
        $this->assertNull($private->ico);
    }

    public function test_container_stand_merges_or_stays_standalone(): void
    {
        $this->owner();
        $this->writeFile();
        $this->importLegacy();

        $this->assertNull(CleaningObject::withoutGlobalScopes()->where('name', 'Test Street č.13,15 kont.stojisko')->first());
        $orphan = $this->objectNamed('Orphan Street č.5 kont. stojisko');
        $this->assertSame(ObjectTypeEnum::CommonAreas, $orphan->type);
    }

    public function test_object_type_street_billing_and_address_mapping(): void
    {
        $this->owner();
        $this->writeFile();
        $this->importLegacy();

        $house = $this->objectNamed('Test Street č.13,15');
        $this->assertSame(ObjectTypeEnum::ResidentialBuilding, $house->type);
        $this->assertSame('Test Street 13,15', $house->street);
        $this->assertSame('Spoločenstvo vlastníkov bytov Test Street 13', $house->billing_name);
        $this->assertNull($house->postal_code);
        $this->assertSame('Bratislava', $house->city);
        $this->assertTrue($house->is_active);

        $office = $this->objectNamed('Kancelária Test');
        $this->assertSame(ObjectTypeEnum::Office, $office->type);
        $this->assertNull($office->street);
        $this->assertSame('Header Four s.r.o.', $office->billing_name);

        $bare = $this->objectNamed('Beta Street č.7a');
        $this->assertNull($bare->billing_name);
        $this->assertSame('Bratislava', $bare->city);
        $this->assertNull($bare->postal_code);
        $this->assertSame('Beta Street 7a', $bare->street);

        $kosice = $this->objectNamed('Beta Street č.1-3');
        $this->assertNull($kosice->postal_code);
        $this->assertSame('Bratislava', $kosice->city);
    }

    public function test_second_run_creates_nothing(): void
    {
        $this->owner();
        $this->writeFile();
        $this->importLegacy();
        $before = $this->counts();

        [$code, $output] = $this->importLegacy();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Created: 0 firms, 0 clients, 0 objects, 0 recurring invoices, 0 drafts.', $output);
        $this->assertSame($before, $this->counts());
    }

    /**
     * @param  array<string, string>  $codes
     * @return array<string, mixed>
     */
    private function withPostalCodes(array $codes): array
    {
        return [...$this->fixture(), 'object_postal_codes' => $codes];
    }

    public function test_postal_codes_fill_blank_on_existing_objects_only_and_second_run_fills_nothing(): void
    {
        $this->owner();
        $this->writeFile();
        $this->importLegacy();
        $this->assertNull($this->objectNamed('Beta Street č.7a')->postal_code);
        $this->writeFile($this->withPostalCodes([
            '  beta street č.7A ' => '821 07',
            'Kancelária Test' => '82107',
        ]));
        $before = Activity::query()->count();

        [$code, $output] = $this->importLegacy();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Postal codes filled: 2, corrected: 0, skipped invalid: 0.', $output);
        $this->assertSame('821 07', $this->objectNamed('Beta Street č.7a')->postal_code);
        $this->assertSame('821 07', $this->objectNamed('Kancelária Test')->postal_code);
        $this->assertNull($this->objectNamed('Test Street č.13,15')->postal_code);
        $this->assertGreaterThan($before, Activity::query()->count());

        [, $output] = $this->importLegacy();

        $this->assertStringContainsString('Postal codes filled: 0, corrected: 0, skipped invalid: 0.', $output);
    }

    public function test_postal_code_is_set_when_the_object_is_created(): void
    {
        $this->owner();
        $this->writeFile($this->withPostalCodes(['Beta Street č.7a' => '82107']));

        [, $output] = $this->importLegacy();

        $this->assertStringContainsString('Postal codes filled: 1, corrected: 0, skipped invalid: 0.', $output);
        $this->assertSame('821 07', $this->objectNamed('Beta Street č.7a')->postal_code);
    }

    public function test_invalid_postal_code_is_skipped_and_counted(): void
    {
        $this->owner();
        $this->writeFile();
        $this->importLegacy();
        $this->writeFile($this->withPostalCodes(['Beta Street č.7a' => '8210', 'Kancelária Test' => 'abc 12']));

        [, $output] = $this->importLegacy();

        $this->assertStringContainsString('Postal codes filled: 0, corrected: 0, skipped invalid: 2.', $output);
        $this->assertNull($this->objectNamed('Beta Street č.7a')->postal_code);
    }

    public function test_postal_codes_dry_run_counts_but_writes_nothing(): void
    {
        $this->owner();
        $this->writeFile();
        $this->importLegacy();
        $this->writeFile($this->withPostalCodes(['Beta Street č.7a' => '821 07']));

        [, $output] = $this->importLegacy(['--dry-run' => true]);

        $this->assertStringContainsString('Postal codes filled: 1, corrected: 0, skipped invalid: 0.', $output);
        $this->assertNull($this->objectNamed('Beta Street č.7a')->postal_code);
    }

    public function test_missing_postal_code_key_is_a_no_op(): void
    {
        $this->owner();
        $this->writeFile();
        $this->importLegacy();

        [, $output] = $this->importLegacy();

        $this->assertStringContainsString('Postal codes filled: 0, corrected: 0, skipped invalid: 0.', $output);
        $this->assertNull($this->objectNamed('Beta Street č.7a')->postal_code);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->owner();
        $this->writeFile();
        $before = $this->counts();

        [$code, $output] = $this->importLegacy(['--dry-run' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('Created: 3 firms, 5 clients, 5 objects, 0 recurring invoices, 0 drafts.', $output);
        $this->assertSame($before, $this->counts());
    }

    public function test_soft_deleted_object_is_not_recreated(): void
    {
        $this->owner();
        $this->writeFile();
        $this->importLegacy();
        $this->objectNamed('Kancelária Test')->delete();
        $before = $this->counts();

        [$code, $output] = $this->importLegacy();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Created: 0 firms, 0 clients, 0 objects, 0 recurring invoices, 0 drafts.', $output);
        $this->assertSame($before, $this->counts());
    }

    public function test_existing_firm_is_reused_and_only_blanks_are_filled(): void
    {
        $owner = $this->owner(ico: '22222222', supplier: new TenantSupplierProfileData(address_line: 'Own Street 1'));
        $this->writeFile();

        [$code, $output] = $this->importLegacy();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Created: 2 firms', $output);
        $this->assertSame(3, Tenant::query()->count());
        $existing = $this->tenant('22222222');
        $this->assertSame('Placeholder Firm 22222222', $existing->name);
        $this->assertSame('Own Street 1', $existing->address_line);
        $this->assertSame('Alpha City', $existing->city);
        $this->assertSame('SK8975000000000123456789', $existing->iban);
        $this->assertTrue($owner->hasActiveMembership($existing->id));
        $this->assertCount(2, $this->clientsOf($existing));
    }

    public function test_existing_firm_without_owner_membership_fails_and_writes_nothing(): void
    {
        $this->owner();
        $this->owner('other@example.com', '22222222');
        $this->writeFile();
        $before = $this->counts();

        [$code, $output] = $this->importLegacy();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('firm #2', $output);
        $this->assertSame($before, $this->counts());
    }

    public function test_copy_settings_applies_to_new_and_existing_firms(): void
    {
        $this->owner(ico: '22222222');
        $existingTenant = $this->tenant('22222222');
        $existingTenant->forceFill(['contact_email' => 'own@example.com', 'name' => 'Configured Firm'])->save();
        app()->instance('current_tenant_id', $existingTenant->id);
        $this->interfaceOf($existingTenant)->forceFill(['default_header_text' => 'Own header'])->save();
        $existingTenant->forceFill(['representative_name' => 'Own Rep', 'signing_place' => 'Own Town', 'contact_phone' => '+421911111111'])->save();
        $this->interfaceOf($existingTenant)->forceFill([
            'default_footer_text' => 'Footer text', 'default_quote_included_text' => 'Included text',
            'invoice_mail_subject' => 'Mail subject', 'invoice_mail_body' => 'Mail body',
        ])->save();
        $signature = $existingTenant->addMediaFromString('sig-bytes')->usingFileName('sig.png')->toMediaCollection('signature');
        $existingTenant->update(['signature_media_id' => $signature->id]);
        app()->forgetInstance('current_tenant_id');
        $this->writeFile();

        [$code, $output] = $this->importLegacy(['--copy-settings-from' => '22 222 222']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Created: 2 firms', $output);

        $target = $this->tenant('33333330')->load('interface');
        $this->assertSame('own@example.com', $target->contact_email);
        $this->assertSame('+421911111111', $target->contact_phone);
        $this->assertSame('Own Rep', $target->representative_name);
        $this->assertSame('Own Town', $target->signing_place);
        $this->assertSame('Own header', $this->interfaceOf($target)->default_header_text);
        $this->assertSame('Footer text', $this->interfaceOf($target)->default_footer_text);
        $this->assertSame('Included text', $this->interfaceOf($target)->default_quote_included_text);
        $this->assertSame('Mail subject', $this->interfaceOf($target)->invoice_mail_subject);
        $this->assertSame('Mail body', $this->interfaceOf($target)->invoice_mail_body);

        $this->assertNotNull($target->signature_media_id);
        $this->assertSame($target->id, $target->signatureMedia?->tenant_id);
        $this->assertNotSame($signature->id, $target->signature_media_id);
    }

    public function test_existing_firm_keeps_filled_contact_and_own_signature(): void
    {
        $this->owner(ico: '77777770');
        $source = $this->tenant('77777770');
        $source->forceFill(['contact_email' => 'source@example.com', 'contact_phone' => '+421900000000'])->save();
        app()->instance('current_tenant_id', $source->id);
        $sourceSignature = $source->addMediaFromString('source-sig')->usingFileName('source.png')->toMediaCollection('signature');
        $source->update(['signature_media_id' => $sourceSignature->id]);
        app()->forgetInstance('current_tenant_id');

        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $target = Tenant::create(['owner_id' => $owner->id, 'name' => 'Target Firm', 'ico' => '22222222', 'country' => 'SK', 'is_active' => true, 'contact_email' => 'own@example.com']);
        TenantMembership::create(['user_id' => $owner->id, 'tenant_id' => $target->id, 'is_active' => true, 'joined_at' => now()]);
        app()->instance('current_tenant_id', $target->id);
        $ownSignature = $target->addMediaFromString('own-sig')->usingFileName('own.png')->toMediaCollection('signature');
        $target->update(['signature_media_id' => $ownSignature->id]);
        app()->forgetInstance('current_tenant_id');
        $this->writeFile();

        [$code] = $this->importLegacy(['--copy-settings-from' => '77777770']);

        $this->assertSame(0, $code);
        $target->refresh();
        $this->assertSame('own@example.com', $target->contact_email);
        $this->assertSame('+421900000000', $target->contact_phone);
        $this->assertSame('own.png', $target->signatureMedia?->file_name);
    }

    public function test_copy_settings_media_is_skipped_on_dry_run_and_second_run(): void
    {
        $this->owner(ico: '77777770');
        $source = $this->tenant('77777770');
        app()->instance('current_tenant_id', $source->id);
        $signature = $source->addMediaFromString('source-sig')->usingFileName('source.png')->toMediaCollection('signature');
        $source->update(['signature_media_id' => $signature->id]);
        app()->forgetInstance('current_tenant_id');
        $this->writeFile();

        [, $dry] = $this->importLegacy(['--copy-settings-from' => '77777770', '--dry-run' => true]);
        $this->assertSame(0, Media::query()->where('model_type', '!=', '')->where('model_id', '!=', $source->id)->count());
        $this->assertStringContainsString('DRY RUN', $dry);

        $this->importLegacy(['--copy-settings-from' => '77777770']);
        $mediaAfterFirst = Media::query()->count();
        $this->assertSame(4, $mediaAfterFirst);

        $this->importLegacy(['--copy-settings-from' => '77777770']);
        $this->assertSame($mediaAfterFirst, Media::query()->count());
    }

    public function test_unknown_or_inactive_owner_fails_with_nothing_written(): void
    {
        $this->owner();
        $this->writeFile();
        $before = $this->counts();

        [$unknown] = $this->importLegacy(['--owner' => 'missing@example.com']);
        User::query()->where('email', 'owner@example.com')->update(['is_active' => false]);
        [$inactive] = $this->importLegacy();

        $this->assertSame(1, $unknown);
        $this->assertSame(1, $inactive);
        $this->assertSame($before, $this->counts());
    }

    public function test_unknown_settings_source_fails(): void
    {
        $this->owner();
        $this->writeFile();
        $before = $this->counts();

        [$code] = $this->importLegacy(['--copy-settings-from' => '00000001']);

        $this->assertSame(1, $code);
        $this->assertSame($before, $this->counts());
    }

    public function test_missing_or_invalid_file_fails(): void
    {
        $this->owner();

        [$missing] = $this->importLegacy();
        Storage::disk('local')->put(self::PATH, '{not json');
        [$invalid] = $this->importLegacy();
        Storage::disk('local')->put(self::PATH, (string) json_encode(['firms' => []]));
        [$shape, $output] = $this->importLegacy();

        $this->assertSame(1, $missing);
        $this->assertSame(1, $invalid);
        $this->assertSame(1, $shape);
        $this->assertStringContainsString('clients', $output);
    }

    public function test_invalid_record_fails_names_the_record_and_writes_nothing(): void
    {
        $this->owner();
        $payload = array_replace_recursive($this->fixture(), ['firms' => [1 => ['company_iban' => 'not an iban']]]);
        $this->writeFile($payload);
        $before = $this->counts();

        [$code, $output] = $this->importLegacy();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('firm #3', $output);
        $this->assertSame($before, $this->counts());
    }

    public function test_invalid_client_rolls_back_earlier_firms(): void
    {
        $this->owner();
        $payload = array_replace_recursive($this->fixture(), ['clients' => [0 => ['register' => ['name' => str_repeat('x', 300)]]]]);
        $this->writeFile($payload);
        $before = $this->counts();

        [$code, $output] = $this->importLegacy();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('client #1', $output);
        $this->assertSame($before, $this->counts());
    }

    public function test_activity_rows_carry_owner_and_firm(): void
    {
        $owner = $this->owner();
        $this->writeFile();
        $this->importLegacy();

        $alpha = $this->tenant('22222222');
        $client = Client::withoutGlobalScopes()->where('tenant_id', $alpha->id)->firstOrFail();
        $object = $this->objectNamed('Kancelária Test');

        foreach ([$client, $object] as $subject) {
            $activity = Activity::query()->where('subject_id', $subject->id)->where('event', 'created')->firstOrFail();
            $this->assertSame($owner->id, $activity->causer_id);
            $this->assertSame($alpha->id, $activity->tenant_id);
        }
    }

    public function test_reads_the_file_from_the_given_disk(): void
    {
        $this->owner();
        $this->writeFile(null, 's3');

        [$local] = $this->importLegacy();
        [$remote, $output] = $this->importLegacy(['--disk' => 's3']);

        $this->assertSame(1, $local);
        $this->assertSame(0, $remote);
        $this->assertStringContainsString('Created: 3 firms', $output);
    }

    public function test_output_hides_names_unless_details(): void
    {
        $this->owner();
        $this->writeFile();

        [, $plain] = $this->importLegacy(['--dry-run' => true]);
        [, $details] = $this->importLegacy(['--dry-run' => true, '--details' => true]);

        $this->assertStringNotContainsString('Alpha Cleaning', $plain);
        $this->assertStringNotContainsString('Kancelária Test', $plain);
        $this->assertStringContainsString('Alpha Cleaning', $details);
        $this->assertStringContainsString('Kancelária Test', $details);
    }

    public function test_colour_is_not_copied_from_the_settings_source(): void
    {
        $this->owner(ico: '77777770');
        $source = $this->tenant('77777770');
        $this->interfaceOf($source)->forceFill(['color' => TenantColorEnum::cases()[0]])->save();
        $this->writeFile();

        [$code] = $this->importLegacy(['--copy-settings-from' => '77777770']);

        $this->assertSame(0, $code);
        $this->assertNull($this->interfaceOf($this->tenant('33333330'))->color);
    }

    public function test_settings_source_firm_is_left_untouched(): void
    {
        $this->owner(ico: '77777770');
        $source = $this->tenant('77777770');
        $source->forceFill(['contact_email' => 'source@example.com'])->save();
        app()->instance('current_tenant_id', $source->id);
        $signature = $source->addMediaFromString('sig-bytes')->usingFileName('sig.png')->toMediaCollection('signature');
        $source->update(['signature_media_id' => $signature->id]);
        app()->forgetInstance('current_tenant_id');
        $this->writeFile();
        $mediaBefore = Media::query()->where('model_id', $source->id)->count();
        $before = $source->fresh()?->toArray();

        [$code] = $this->importLegacy(['--copy-settings-from' => '77777770']);

        $this->assertSame(0, $code);
        $this->assertSame($before, $source->fresh()?->toArray());
        $this->assertSame($mediaBefore, Media::query()->where('model_id', $source->id)->count());
        $this->assertSame($signature->id, $source->fresh()?->signature_media_id);
    }

    public function test_context_is_restored_after_success_and_failure(): void
    {
        $this->owner();
        $team = app(PermissionRegistrar::class)->getPermissionsTeamId();
        $this->writeFile();

        $this->importLegacy();

        $this->assertFalse(app()->bound('current_tenant_id'));
        $this->assertSame($team, app(PermissionRegistrar::class)->getPermissionsTeamId());

        $this->writeFile(array_replace_recursive($this->fixture(), ['clients' => [0 => ['register' => ['name' => str_repeat('x', 300)]]]]));
        Tenant::query()->where('ico', '!=', '99999999')->delete();
        $this->importLegacy();

        $this->assertFalse(app()->bound('current_tenant_id'));
        $this->assertSame($team, app(PermissionRegistrar::class)->getPermissionsTeamId());
    }

    public function test_database_error_prints_only_the_sqlstate(): void
    {
        $this->owner();
        $this->writeFile();
        Client::creating(fn () => throw new QueryException('pgsql', 'insert into clients values (?)', ['Secret Name'], new PDOException('SQLSTATE[23505]: secret detail', 23505)));

        [$code, $output] = $this->importLegacy();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('SQLSTATE', $output);
        $this->assertStringNotContainsString('Secret Name', $output);
        $this->assertStringNotContainsString('secret detail', $output);
    }

    public function test_media_copy_failure_exits_non_zero_without_leaking_and_rerun_finishes(): void
    {
        $this->owner(ico: '77777770');
        $source = $this->tenant('77777770');
        app()->instance('current_tenant_id', $source->id);
        $signature = $source->addMediaFromString('sig-bytes')->usingFileName('sig.png')->toMediaCollection('signature');
        $source->update(['signature_media_id' => $signature->id]);
        app()->forgetInstance('current_tenant_id');
        $this->writeFile();

        $state = new ArrayObject(['fail' => true]);
        Media::creating(function () use ($state): void {
            if ($state['fail']) {
                throw new RuntimeException('/secret/bucket/path');
            }
        });

        [$code, $output] = $this->importLegacy(['--copy-settings-from' => '77777770']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Media copy failed (firm #2)', $output);
        $this->assertStringNotContainsString('/secret/bucket/path', $output);
        $this->assertSame(2, Client::withoutGlobalScopes()->where('tenant_id', $this->tenant('22222222')->id)->count());

        $state['fail'] = false;
        [$rerun] = $this->importLegacy(['--copy-settings-from' => '77777770']);

        $this->assertSame(0, $rerun);
        $this->assertNotNull($this->tenant('22222222')->signature_media_id);
    }

    public function test_legacy_logo_media_is_not_copied(): void
    {
        $this->owner(ico: '77777770');
        $source = $this->tenant('77777770');
        app()->instance('current_tenant_id', $source->id);
        $source->addMediaFromString('logo-bytes')->usingFileName('logo.png')->toMediaCollection('logo');
        app()->forgetInstance('current_tenant_id');
        $this->writeFile();

        [$code] = $this->importLegacy(['--copy-settings-from' => '77777770']);

        $this->assertSame(0, $code);
        $this->assertSame(0, Media::query()->where('collection_name', 'logo')->where('model_id', '!=', $source->id)->count());
    }
}
