<?php

declare(strict_types=1);

namespace Tests\Feature\Quotes;

use App\Contracts\RendersQuotePdf;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

final class QuoteDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(RendersQuotePdf::class, function (MockInterface $mock): void {
            $mock->shouldReceive('render')->andReturn('%PDF-1.4 fake pdf content');
        });
    }

    // -------------------------------------------------------------------------
    // QuoteService::attachDocument — happy + fail
    // -------------------------------------------------------------------------

    public function test_attach_document_stores_file_on_document_quote(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $file = $this->fakePdf('scan.pdf', 100);

        $result = app(QuoteService::class)->attachDocument($quote, $file);

        $this->assertNotNull($result->getFirstMedia('document'));
        $this->assertSame('scan.pdf', $result->getFirstMedia('document')->file_name);
    }

    public function test_attach_document_replaces_existing_file(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        app(QuoteService::class)->attachDocument($quote, $this->fakePdf('first.pdf', 50));
        $result = app(QuoteService::class)->attachDocument($quote, $this->fakePdf('second.pdf', 50));

        $this->assertCount(1, $result->getMedia('document'));
        $this->assertSame('second.pdf', $result->getFirstMedia('document')->file_name);
    }

    public function test_attach_document_throws_when_quote_is_itemized(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->expectException(ValidationException::class);

        app(QuoteService::class)->attachDocument($quote, $this->fakePdf('scan.pdf', 100));
    }

    // -------------------------------------------------------------------------
    // POST /quotes/{quote}/document — mime + size validation
    // -------------------------------------------------------------------------

    public function test_upload_document_endpoint_accepts_pdf(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.document.store', $quote), [
            'document' => $this->fakePdf('scan.pdf', 100),
        ]);

        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertNotNull($quote->fresh()->getFirstMedia('document'));
    }

    public function test_upload_document_endpoint_accepts_file_exactly_at_size_limit(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.document.store', $quote), [
            'document' => $this->fakePdf('scan.pdf', config('documents.max_size_kb')),
        ]);

        $response->assertRedirect(route('quotes.show', $quote));
    }

    public function test_upload_document_endpoint_rejects_wrong_mime_type(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.document.store', $quote), [
            'document' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ]);

        $response->assertSessionHasErrors('document');
        $this->assertNull($quote->fresh()->getFirstMedia('document'));
    }

    public function test_upload_document_endpoint_rejects_oversized_file(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.document.store', $quote), [
            'document' => $this->fakePdf('scan.pdf', config('documents.max_size_kb') + 1),
        ]);

        $response->assertSessionHasErrors('document');
    }

    public function test_upload_document_endpoint_rejects_when_quote_is_itemized(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->post(route('quotes.document.store', $quote), [
            'document' => $this->fakePdf('scan.pdf', 100),
        ]);

        $response->assertSessionHasErrors('document');
    }

    public function test_upload_document_endpoint_403_for_non_draft_quote(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->sent()->create(['tenant_id' => $tenant->id]);

        $response = $this->post(route('quotes.document.store', $quote), [
            'document' => $this->fakePdf('scan.pdf', 100),
        ]);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // GET /quotes/{quote}/pdf — document branch
    // -------------------------------------------------------------------------

    public function test_pdf_returns_404_for_document_quote_without_media(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);

        $response = $this->get(route('quotes.pdf', $quote));

        $response->assertNotFound();
    }

    public function test_pdf_streams_stored_file_for_document_quote(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);
        app(QuoteService::class)->attachDocument($quote, UploadedFile::fake()->image('scan.jpg')->size(100));

        $response = $this->get(route('quotes.pdf', $quote));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_pdf_renders_blade_pdf_for_itemized_quote(): void
    {
        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $response = $this->get(route('quotes.pdf', $quote));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // -------------------------------------------------------------------------
    // duplicate — document mode copies the file (D6)
    // -------------------------------------------------------------------------

    public function test_duplicate_copies_document_file_to_new_quote(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);
        app(QuoteService::class)->attachDocument($quote, $this->fakePdf('scan.pdf', 100));

        $dupe = app(QuoteService::class)->duplicate($quote->fresh());

        $this->assertNotNull($dupe->getFirstMedia('document'));
        $this->assertSame('scan.pdf', $dupe->getFirstMedia('document')->file_name);
        $this->assertCount(0, $dupe->items);
    }

    // -------------------------------------------------------------------------
    // Soft delete × MediaLibrary (D9)
    // -------------------------------------------------------------------------

    public function test_soft_delete_keeps_media_file_on_disk(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);
        app(QuoteService::class)->attachDocument($quote, $this->fakePdf('scan.pdf', 100));

        $media = $quote->fresh()->getFirstMedia('document');
        $this->assertNotNull($media);

        $quote->delete();

        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk(config('documents.disk'))->assertExists($media->id . '/' . $media->file_name);
    }

    public function test_force_delete_removes_media_file_from_disk(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $quote = Quote::factory()->document()->create(['tenant_id' => $tenant->id]);
        app(QuoteService::class)->attachDocument($quote, $this->fakePdf('scan.pdf', 100));

        $media = $quote->fresh()->getFirstMedia('document');
        $mediaId = $media->id;
        $path = $mediaId . '/' . $media->file_name;

        $quote->forceDelete();

        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
        Storage::disk(config('documents.disk'))->assertMissing($path);
    }

    // -------------------------------------------------------------------------
    // Tenant isolation
    // -------------------------------------------------------------------------

    public function test_document_endpoints_are_tenant_isolated(): void
    {
        Storage::fake(config('documents.disk'));

        $user = $this->actingAsTenantUser('Admin');

        $otherTenant = Tenant::factory()->create();
        $otherQuote = Quote::factory()->document()->create(['tenant_id' => $otherTenant->id]);

        $this->get(route('quotes.show', $otherQuote))->assertNotFound();
        $this->get(route('quotes.pdf', $otherQuote))->assertNotFound();
        $this->post(route('quotes.attach-client', $otherQuote), ['client_id' => (string) Str::uuid()])->assertNotFound();
        $this->post(route('quotes.document.store', $otherQuote), [
            'document' => $this->fakePdf('scan.pdf', 10),
        ])->assertNotFound();
    }

    private function fakePdf(string $name, int $kilobytes = 10): UploadedFile
    {
        $targetBytes = $kilobytes * 1024;
        $header = "%PDF-1.4\n";
        $content = $header . str_repeat('A', max(0, $targetBytes - strlen($header)));

        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
