<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Contracts\RendersQuotePdf;
use App\Enums\DocumentTypeEnum;
use App\Models\Document;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\Expectation;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class QuoteApiTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $leader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->leader = $this->member($this->alpha, 'Vedúca');
    }

    public function test_lists_quotes_of_the_active_firm_only(): void
    {
        $mine = Quote::factory()->create(['tenant_id' => $this->alpha->id]);
        $foreign = Quote::factory()->create(['tenant_id' => $this->beta->id]);

        $ids = $this->apiAs($this->leader, $this->alpha)->getJson('/api/quotes')
            ->assertOk()
            ->json('data.*.id');

        $this->assertContains($mine->id, (array) $ids);
        $this->assertNotContains($foreign->id, (array) $ids);
    }

    public function test_filters_by_status(): void
    {
        Quote::factory()->create(['tenant_id' => $this->alpha->id]);
        $sent = Quote::factory()->sent()->create(['tenant_id' => $this->alpha->id]);

        $ids = $this->apiAs($this->leader, $this->alpha)->getJson('/api/quotes?filter[status]=sent')
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$sent->id], $ids);
    }

    public function test_shows_quote_detail(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->alpha->id]);

        $this->apiAs($this->leader, $this->alpha)->getJson("/api/quotes/{$quote->id}")
            ->assertOk()
            ->assertJsonPath('id', $quote->id)
            ->assertJsonStructure(['items', 'vat_breakdown', 'invoices', 'contracts']);
    }

    public function test_foreign_firm_quote_is_not_found_in_the_active_firm(): void
    {
        $this->member($this->beta, 'Vedúca', $this->leader);
        $foreign = Quote::factory()->create(['tenant_id' => $this->beta->id]);

        $this->apiAs($this->leader, $this->alpha)->getJson("/api/quotes/{$foreign->id}")->assertNotFound();
        $this->apiAs($this->leader, $this->alpha)->getJson("/api/quotes/{$foreign->id}/pdf")->assertNotFound();
    }

    public function test_header_for_a_firm_without_membership_is_forbidden(): void
    {
        $this->apiAs($this->leader, $this->beta)->getJson('/api/quotes')->assertForbidden();
    }

    public function test_role_without_view_quotes_is_forbidden(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');
        $quote = Quote::factory()->create(['tenant_id' => $this->alpha->id]);

        $this->apiAs($cleaner, $this->alpha)->getJson('/api/quotes')->assertForbidden();
        $this->apiAs($cleaner, $this->alpha)->getJson("/api/quotes/{$quote->id}/pdf")->assertForbidden();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/quotes')->assertUnauthorized();
    }

    public function test_pdf_is_streamed_inline_as_pdf(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->alpha->id]);
        /** @var Expectation $expectation */
        $expectation = $this->mock(RendersQuotePdf::class)->shouldReceive('render');
        $expectation->once()->andReturn('%PDF-1.4 fake');

        $response = $this->apiAs($this->leader, $this->alpha)->get("/api/quotes/{$quote->id}/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_document_quote_without_file_has_no_pdf(): void
    {
        $quote = Quote::factory()->document()->create(['tenant_id' => $this->alpha->id]);

        $this->apiAs($this->leader, $this->alpha)->get("/api/quotes/{$quote->id}/pdf")->assertNotFound();
    }

    public function test_per_page_is_clamped(): void
    {
        Quote::factory()->count(3)->create(['tenant_id' => $this->alpha->id]);

        $this->apiAs($this->leader, $this->alpha)->getJson('/api/quotes?per_page=-5')->assertOk()->assertJsonPath('per_page', 1);
        $this->apiAs($this->leader, $this->alpha)->getJson('/api/quotes?per_page=100000')->assertOk()->assertJsonPath('per_page', 100);
    }

    public function test_document_quote_with_file_streams_the_stored_file_inline(): void
    {
        $quote = Quote::factory()->document()->create(['tenant_id' => $this->alpha->id]);
        $this->bindTenant($this->alpha);
        $file = UploadedFile::fake()->image('scan.png', 20, 20);
        $document = Document::factory()->create([
            'tenant_id' => $this->alpha->id,
            'documentable_type' => $quote->getMorphClass(),
            'documentable_id' => $quote->id,
            'type' => DocumentTypeEnum::Quote,
        ]);
        $document->addMedia($file->getPathname())
            ->usingFileName('scan.png')
            ->toMediaCollection('file');
        $this->consoleContext();

        $response = $this->apiAs($this->leader, $this->alpha)->get("/api/quotes/{$quote->id}/pdf");

        $response->assertOk();
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('scan.png', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_detail_and_list_expose_period_totals_and_item_billing_period(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->alpha->id, 'period_totals' => [
            ['billing_period' => 'one_off', 'subtotal' => '10.00', 'vat_amount' => '0.00', 'total' => '10.00'],
            ['billing_period' => 'monthly', 'subtotal' => '5.00', 'vat_amount' => '0.00', 'total' => '5.00'],
        ]]);
        QuoteItem::factory()->monthly()->create(['tenant_id' => $this->alpha->id, 'quote_id' => $quote->id]);

        $this->apiAs($this->leader, $this->alpha)->getJson("/api/quotes/{$quote->id}")
            ->assertOk()
            ->assertJsonPath('total', $quote->total)
            ->assertJsonPath('period_totals.1.billing_period', 'monthly')
            ->assertJsonPath('items.0.billing_period', 'monthly');

        $this->apiAs($this->leader, $this->alpha)->getJson('/api/quotes')
            ->assertOk()
            ->assertJsonPath('data.0.period_totals.0.billing_period', 'one_off');
    }
}
