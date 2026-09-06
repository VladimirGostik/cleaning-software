<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Enums\QuoteKindEnum;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class QuoteDocumentTest extends TestCase
{
    use RefreshDatabase;

    private string $documentDisk = 'local';

    protected function setUp(): void
    {
        parent::setUp();
        $disk = config('quotes.document.disk');
        $this->documentDisk = is_string($disk) ? $disk : 'local';
        Storage::fake('public');
        Storage::fake($this->documentDisk);
    }

    private function fakePdf(string $name = 'doc.pdf', int $kb = 1): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".str_repeat('A', $kb * 1024));
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
    private function documentPayload(string $uuid, array $overrides = []): array
    {
        return array_merge([
            'client_id' => null,
            'cleaning_object_id' => null,
            'subject' => 'Document quote',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'note' => null,
            'items' => [],
            'customer_name' => 'Doc Customer',
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
            'number' => null,
            'document_uuid' => $uuid,
            'kind' => QuoteKindEnum::Document->value,
            'currency' => 'EUR',
        ], $overrides);
    }

    public function test_store_document_quote_moves_media_to_configured_disk(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePdf());

        $this->post(route('quotes.store'), $this->documentPayload($uuid))->assertSessionDoesntHaveErrors();

        $quote = Quote::query()->sole();
        $media = $quote->getFirstMedia('document');
        $this->assertNotNull($media);
        $this->assertSame($this->documentDisk, $media->disk);
        $this->assertDatabaseCount('temporary_uploads', 0);
    }

    public function test_reupload_replaces_existing_document(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $firstUuid = $this->uploadAndGetUuid($this->fakePdf('first.pdf'));
        $this->post(route('quotes.store'), $this->documentPayload($firstUuid));
        $quote = Quote::query()->sole();

        $secondUuid = $this->uploadAndGetUuid($this->fakePdf('second.pdf'));
        $this->put(route('quotes.update', $quote), $this->documentPayload($secondUuid))->assertSessionDoesntHaveErrors();

        $quote->refresh();
        $this->assertCount(1, $quote->getMedia('document'));
        $this->assertSame('second.pdf', $quote->getFirstMedia('document')?->file_name);
    }

    public function test_document_uuid_on_itemized_quote_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePdf());

        $payload = $this->documentPayload($uuid, [
            'kind' => QuoteKindEnum::Itemized->value,
            'items' => [
                ['id' => null, 'description' => 'Item', 'frequency' => null, 'quantity' => 1, 'unit' => null, 'unit_price' => 10, 'discount_percent' => 0, 'vat_rate' => 0],
            ],
        ]);

        $this->post(route('quotes.store'), $payload)->assertSessionHasErrors('document_uuid');
    }

    public function test_document_kind_without_uuid_fails_on_create(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        $payload = $this->documentPayload('', ['document_uuid' => null]);

        $this->post(route('quotes.store'), $payload)->assertSessionHasErrors('document_uuid');
    }

    public function test_wrong_mime_type_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid(UploadedFile::fake()->createWithContent('note.txt', 'hello world'));

        $this->post(route('quotes.store'), $this->documentPayload($uuid))->assertSessionHasErrors('document_uuid');
    }

    public function test_oversized_document_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        config(['quotes.document.max_size_kb' => 100]);
        $uuid = $this->uploadAndGetUuid($this->fakePdf('huge.pdf', 200));

        $this->post(route('quotes.store'), $this->documentPayload($uuid))->assertSessionHasErrors('document_uuid');
    }

    public function test_other_users_temp_uuid_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePdf());

        $stranger = User::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant, $stranger);

        $this->post(route('quotes.store'), $this->documentPayload($uuid))->assertSessionHasErrors('document_uuid');
    }

    public function test_other_tenants_temp_uuid_fails_validation(): void
    {
        $tenantA = Tenant::factory()->create();
        $user = $this->actingAsTenantUser('Admin', $tenantA);
        $uuid = $this->uploadAndGetUuid($this->fakePdf());

        $tenantB = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenantB, $user);

        $this->post(route('quotes.store'), $this->documentPayload($uuid))->assertSessionHasErrors('document_uuid');
    }

    public function test_pdf_route_streams_document_file(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePdf());
        $this->post(route('quotes.store'), $this->documentPayload($uuid));
        $quote = Quote::query()->sole();

        $response = $this->get(route('quotes.pdf', $quote));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_route_404_when_no_document_attached(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $this->actingAsTenantUser('Admin', $tenant);
        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $this->get(route('quotes.pdf', $quote))->assertNotFound();
    }

    public function test_duplicate_copies_document_media(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePdf());
        $this->post(route('quotes.store'), $this->documentPayload($uuid));
        $quote = Quote::query()->sole();

        $duplicate = app(QuoteService::class)->duplicate($quote);

        $this->assertCount(1, $duplicate->getMedia('document'));
    }

    public function test_soft_delete_keeps_document_file(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $uuid = $this->uploadAndGetUuid($this->fakePdf());
        $this->post(route('quotes.store'), $this->documentPayload($uuid));
        $quote = Quote::query()->sole();

        $this->delete(route('quotes.destroy', $quote));

        $trashed = Quote::withTrashed()->findOrFail($quote->id);
        $this->assertNotNull($trashed->getFirstMedia('document'));
    }

    public function test_send_on_document_quote_fails(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->send($quote);
    }
}
