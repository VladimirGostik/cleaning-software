<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Contracts\RendersInvoicePdf;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\Expectation;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class InvoiceApiTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->accountant = $this->member($this->alpha, 'Účtovníčka');
    }

    public function test_lists_invoices_of_the_active_firm_only(): void
    {
        $mine = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id]);
        $foreign = Invoice::factory()->issued()->create(['tenant_id' => $this->beta->id]);

        $ids = $this->apiAs($this->accountant, $this->alpha)->getJson('/api/invoices')
            ->assertOk()
            ->json('data.*.id');

        $this->assertContains($mine->id, (array) $ids);
        $this->assertNotContains($foreign->id, (array) $ids);
    }

    public function test_filters_by_status(): void
    {
        $issued = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id]);
        $paid = Invoice::factory()->paid()->create(['tenant_id' => $this->alpha->id]);

        $ids = $this->apiAs($this->accountant, $this->alpha)->getJson('/api/invoices?filter[status]=paid')
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$paid->id], $ids);
        $this->assertNotContains($issued->id, (array) $ids);
    }

    public function test_filters_by_search_over_number_and_customer(): void
    {
        $byNumber = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'number' => 'FA-7741', 'customer_name' => 'Zebra s.r.o.']);
        $byCustomer = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'number' => 'FA-1000', 'customer_name' => 'Kvetinárstvo 7741']);
        $other = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'number' => 'FA-2000', 'customer_name' => 'Iný odberateľ']);

        $api = $this->apiAs($this->accountant, $this->alpha);

        $ids = (array) $api->getJson('/api/invoices?filter[search]=7741')->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([$byNumber->id, $byCustomer->id], $ids);

        $ids = (array) $api->getJson('/api/invoices?filter[search]=Iný')->assertOk()->json('data.*.id');
        $this->assertSame([$other->id], $ids);
    }

    public function test_filters_by_issue_date_range(): void
    {
        $before = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'issue_date' => '2026-09-30']);
        $first = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'issue_date' => '2026-10-01']);
        $last = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'issue_date' => '2026-10-31']);
        $after = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'issue_date' => '2026-11-01']);

        $ids = (array) $this->apiAs($this->accountant, $this->alpha)
            ->getJson('/api/invoices?filter[issue_date]=between:2026-10-01,2026-10-31')
            ->assertOk()
            ->json('data.*.id');

        $this->assertEqualsCanonicalizing([$first->id, $last->id], $ids);
        $this->assertNotContains($before->id, $ids);
        $this->assertNotContains($after->id, $ids);
    }

    public function test_shows_invoice_detail(): void
    {
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id]);

        $this->apiAs($this->accountant, $this->alpha)->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('id', $invoice->id)
            ->assertJsonStructure(['items', 'payments', 'vat_breakdown', 'supplier']);
    }

    public function test_foreign_firm_invoice_is_not_found_in_the_active_firm(): void
    {
        $this->member($this->beta, 'Účtovníčka', $this->accountant);
        $foreign = Invoice::factory()->issued()->create(['tenant_id' => $this->beta->id]);

        $this->apiAs($this->accountant, $this->alpha)->getJson("/api/invoices/{$foreign->id}")->assertNotFound();
        $this->apiAs($this->accountant, $this->alpha)->getJson("/api/invoices/{$foreign->id}/pdf")->assertNotFound();
    }

    public function test_header_for_a_firm_without_membership_is_forbidden(): void
    {
        $this->apiAs($this->accountant, $this->beta)->getJson('/api/invoices')->assertForbidden();
    }

    public function test_role_without_view_invoices_is_forbidden(): void
    {
        $leader = $this->member($this->alpha, 'Vedúca');
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id]);

        $this->apiAs($leader, $this->alpha)->getJson('/api/invoices')->assertForbidden();
        $this->apiAs($leader, $this->alpha)->getJson("/api/invoices/{$invoice->id}")->assertForbidden();
        $this->apiAs($leader, $this->alpha)->getJson("/api/invoices/{$invoice->id}/pdf")->assertForbidden();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/invoices')->assertUnauthorized();
    }

    public function test_pdf_is_streamed_inline_as_pdf(): void
    {
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id]);
        $this->fakeRenderer();

        $response = $this->apiAs($this->accountant, $this->alpha)->get("/api/invoices/{$invoice->id}/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_pdf_of_a_draft_renders_like_the_web_route(): void
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->alpha->id]);
        $this->fakeRenderer();

        $this->apiAs($this->accountant, $this->alpha)->get("/api/invoices/{$invoice->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_per_page_is_clamped(): void
    {
        Invoice::factory()->count(3)->issued()->create(['tenant_id' => $this->alpha->id]);

        $this->apiAs($this->accountant, $this->alpha)->getJson('/api/invoices?per_page=-5')
            ->assertOk()
            ->assertJsonPath('per_page', 1);
        $this->apiAs($this->accountant, $this->alpha)->getJson('/api/invoices?per_page=100000')
            ->assertOk()
            ->assertJsonPath('per_page', 100);
        $this->apiAs($this->accountant, $this->alpha)->getJson('/api/invoices?per_page=10')
            ->assertOk()
            ->assertJsonPath('per_page', 10);
    }

    public function test_user_without_mobile_access_permission_is_forbidden(): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->accountant, ['mobile']);

        $this->withHeaders(['X-Tenant-Id' => $this->alpha->id])->getJson('/api/invoices')->assertForbidden();
    }

    public function test_mcp_token_is_forbidden(): void
    {
        $this->actingAsMobile($this->accountant);
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->accountant, ['mcp']);

        $this->withHeaders(['X-Tenant-Id' => $this->alpha->id])->getJson('/api/invoices')->assertForbidden();
    }

    public function test_list_items_carry_sent_at(): void
    {
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'sent_at' => now()]);

        $response = $this->apiAs($this->accountant, $this->alpha)->getJson('/api/invoices')->assertOk();

        $response->assertJsonPath('data.0.id', $invoice->id);
        $this->assertNotNull($response->json('data.0.sent_at'));
    }

    public function test_detail_carries_tenant_id_and_state_flags(): void
    {
        $api = $this->apiAs($this->accountant, $this->alpha);
        $draft = Invoice::factory()->create(['tenant_id' => $this->alpha->id]);
        $issued = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id]);
        $overdue = Invoice::factory()->overdue()->create(['tenant_id' => $this->alpha->id]);
        $paid = Invoice::factory()->paid()->create(['tenant_id' => $this->alpha->id]);

        $api->getJson('/api/invoices/'.$draft->id)->assertOk()
            ->assertJsonPath('tenant_id', $this->alpha->id)
            ->assertJsonPath('can_be_issued', true)
            ->assertJsonPath('can_be_sent', false);
        $api->getJson('/api/invoices/'.$issued->id)->assertJsonPath('can_be_issued', false)->assertJsonPath('can_be_sent', true);
        $api->getJson('/api/invoices/'.$overdue->id)->assertJsonPath('can_be_sent', true);
        $api->getJson('/api/invoices/'.$paid->id)->assertJsonPath('can_be_sent', false);
    }

    public function test_draft_with_incomplete_supplier_cannot_be_issued(): void
    {
        $tenant = $this->firm('Gamma');
        $tenant->update(['address_line' => null]);
        $user = $this->member($tenant, 'Účtovníčka');
        $draft = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->apiAs($user, $tenant)->getJson('/api/invoices/'.$draft->id)->assertOk()->assertJsonPath('can_be_issued', false);
    }

    public function test_stats_counts_only_the_active_firm(): void
    {
        Invoice::factory()->overdue()->create(['tenant_id' => $this->alpha->id]);
        Invoice::factory()->overdue()->count(3)->create(['tenant_id' => $this->beta->id]);

        $this->apiAs($this->accountant, $this->alpha)->getJson('/api/invoices/stats')
            ->assertOk()
            ->assertJsonStructure(['issued_this_month', 'overdue', 'pending', 'recurring_monthly', 'currency'])
            ->assertJsonPath('overdue.count', 1);
    }

    private function fakeRenderer(): void
    {
        /** @var Expectation $expectation */
        $expectation = $this->mock(RendersInvoicePdf::class)->shouldReceive('render');
        $expectation->once()->andReturn('%PDF-1.4 fake');
    }
}
