<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Data\Invoices\InvoiceIssueData;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\User;
use App\Notifications\InvoiceIssued;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class InvoiceApiActionsTest extends TestCase
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

    private function api(): static
    {
        return $this->apiAs($this->accountant, $this->alpha);
    }

    public function test_issue_draft_with_empty_body(): void
    {
        $draft = Invoice::factory()->create(['tenant_id' => $this->alpha->id]);

        $this->api()->postJson('/api/invoices/'.$draft->id.'/issue')
            ->assertOk()
            ->assertJsonPath('status', 'issued')
            ->assertJsonPath('can_be_issued', false)
            ->assertJsonPath('can_be_sent', true)
            ->assertJsonPath('issued_by_name', null);

        $draft->refresh();
        $this->assertNotNull($draft->number);
        $this->assertNotSame('', $draft->issued_by_name);
    }

    public function test_issue_respects_manual_number_and_rejects_taken_one(): void
    {
        $api = $this->api();
        $first = Invoice::factory()->create(['tenant_id' => $this->alpha->id]);
        $second = Invoice::factory()->create(['tenant_id' => $this->alpha->id]);

        $api->postJson('/api/invoices/'.$first->id.'/issue', ['number' => 'MOB-001'])->assertOk()->assertJsonPath('number', 'MOB-001');
        $api->postJson('/api/invoices/'.$second->id.'/issue', ['number' => 'MOB-001'])->assertUnprocessable()->assertJsonValidationErrors('number');
    }

    public function test_issue_non_draft_is_422_status(): void
    {
        $issued = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id]);

        $this->api()->postJson('/api/invoices/'.$issued->id.'/issue')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_issue_with_incomplete_supplier_is_422_supplier(): void
    {
        $this->alpha->update(['address_line' => null]);
        $draft = Invoice::factory()->create(['tenant_id' => $this->alpha->id]);

        $this->api()->postJson('/api/invoices/'.$draft->id.'/issue')->assertUnprocessable()->assertJsonValidationErrors('supplier');
    }

    public function test_actions_are_forbidden_without_edit_invoices(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');
        $draft = Invoice::factory()->create(['tenant_id' => $this->alpha->id]);
        $issued = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'a@example.com']);
        $api = $this->apiAs($cleaner, $this->alpha);

        $api->postJson('/api/invoices/'.$draft->id.'/issue')->assertForbidden();
        $api->getJson('/api/invoices/'.$issued->id.'/send-draft')->assertForbidden();
        $api->postJson('/api/invoices/'.$issued->id.'/send')->assertForbidden();
        $api->getJson('/api/invoices/stats')->assertForbidden();
    }

    public function test_foreign_firm_invoice_is_404(): void
    {
        $foreign = Invoice::factory()->create(['tenant_id' => $this->beta->id]);
        $api = $this->api();

        $api->postJson('/api/invoices/'.$foreign->id.'/issue')->assertNotFound();
        $api->getJson('/api/invoices/'.$foreign->id.'/send-draft')->assertNotFound();
        $api->postJson('/api/invoices/'.$foreign->id.'/send')->assertNotFound();
    }

    public function test_mcp_token_is_forbidden(): void
    {
        $draft = Invoice::factory()->create(['tenant_id' => $this->alpha->id]);
        $this->actingAsMobile($this->accountant);
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->accountant, ['mcp']);

        $this->withHeaders(['X-Tenant-Id' => $this->alpha->id])->postJson('/api/invoices/'.$draft->id.'/issue')->assertForbidden();
    }

    public function test_send_draft_resolves_firm_template(): void
    {
        TenantInterface::query()->where('tenant_id', $this->alpha->id)->update([
            'invoice_mail_subject' => 'Faktúra {{invoice.number}}',
            'invoice_mail_body' => 'Objekt {{object.name}}: {{invoice.total}}',
        ]);
        $invoice = Invoice::factory()->issued()->create([
            'tenant_id' => $this->alpha->id,
            'customer_email' => 'client@example.com',
            'object_name' => 'Budova A',
        ]);

        $this->api()->getJson('/api/invoices/'.$invoice->id.'/send-draft')
            ->assertOk()
            ->assertJsonPath('subject', 'Faktúra '.$invoice->number)
            ->assertJsonPath('body', 'Objekt Budova A: '.$invoice->currency->format((float) $invoice->total))
            ->assertJsonPath('recipient_email', 'client@example.com')
            ->assertJsonPath('email_options', [])
            ->assertJsonPath('save_target', null)
            ->assertJsonPath('attachment_name', $invoice->pdfFilenameBase().'.pdf');
    }

    public function test_send_draft_empty_template_uses_lang_default_in_documents_locale_even_for_uk_user(): void
    {
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'c@example.com', 'customer_name' => 'Acme']);

        $locale = config()->string('documents.locale');

        $this->accountant->update(['locale' => 'uk']);

        $this->api()->getJson('/api/invoices/'.$invoice->id.'/send-draft')
            ->assertOk()
            ->assertJsonPath('subject', __('app.invoice_mail_subject', ['number' => $invoice->number], $locale))
            ->assertJsonPath('body', __('app.invoice_mail_greeting', ['name' => 'Acme'], $locale)."\n\n".__('app.invoice_mail_body', [], $locale));
    }

    public function test_send_draft_without_customer_email_returns_options_shape(): void
    {
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => null]);

        $this->api()->getJson('/api/invoices/'.$invoice->id.'/send-draft')
            ->assertOk()
            ->assertJsonPath('recipient_email', null)
            ->assertJsonStructure(['email_options', 'save_target']);
    }

    public function test_send_draft_status_rules(): void
    {
        $api = $this->api();
        $overdue = Invoice::factory()->overdue()->create(['tenant_id' => $this->alpha->id]);
        $draft = Invoice::factory()->create(['tenant_id' => $this->alpha->id]);
        $paid = Invoice::factory()->paid()->create(['tenant_id' => $this->alpha->id]);

        $api->getJson('/api/invoices/'.$overdue->id.'/send-draft')->assertOk();
        $api->getJson('/api/invoices/'.$draft->id.'/send-draft')->assertUnprocessable()->assertJsonValidationErrors('status');
        $api->getJson('/api/invoices/'.$paid->id.'/send-draft')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_send_subject_rejects_line_breaks(): void
    {
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'c@example.com']);

        $this->api()->postJson('/api/invoices/'.$invoice->id.'/send', ['subject' => "Predmet\nBcc: x@evil.test"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subject');
    }

    public function test_issue_rechecks_status_under_lock(): void
    {
        $draft = Invoice::factory()->create(['tenant_id' => $this->alpha->id]);
        $stale = Invoice::query()->findOrFail($draft->id);
        $service = app(InvoiceService::class);

        $service->issue($draft, new InvoiceIssueData, $this->accountant);

        $this->expectException(ValidationException::class);
        $service->issue($stale, new InvoiceIssueData, $this->accountant);
    }

    public function test_send_with_override_notifies_customer_email(): void
    {
        Notification::fake();
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'client@example.com']);

        $this->api()->postJson('/api/invoices/'.$invoice->id.'/send', ['subject' => 'Predmet', 'body' => 'Text'])
            ->assertStatus(202)
            ->assertJsonPath('id', $invoice->id);

        Notification::assertSentOnDemand(
            InvoiceIssued::class,
            fn (InvoiceIssued $n, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'client@example.com'
                && $n->subject === 'Predmet'
                && $n->body === 'Text',
        );
    }

    public function test_send_without_override_and_blank_override_pass_through(): void
    {
        Notification::fake();
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'client@example.com']);
        $api = $this->api();

        $api->postJson('/api/invoices/'.$invoice->id.'/send')->assertStatus(202);
        $api->postJson('/api/invoices/'.$invoice->id.'/send', ['subject' => null, 'body' => null])->assertStatus(202);

        Notification::assertSentOnDemandTimes(InvoiceIssued::class, 2);
        Notification::assertSentOnDemand(InvoiceIssued::class, fn (InvoiceIssued $n) => $n->subject === null && $n->body === null);
    }

    public function test_send_validates_lengths_and_recipient(): void
    {
        $noEmail = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => null]);
        $withEmail = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'c@example.com']);
        $api = $this->api();

        $api->postJson('/api/invoices/'.$noEmail->id.'/send')->assertUnprocessable()->assertJsonValidationErrors('recipient_email');
        $api->postJson('/api/invoices/'.$withEmail->id.'/send', ['subject' => str_repeat('a', 256), 'body' => str_repeat('a', 5001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject', 'body']);
    }

    public function test_send_with_recipient_saves_to_object_billing_email(): void
    {
        Notification::fake();
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => null]);

        $this->api()->postJson('/api/invoices/'.$invoice->id.'/send', ['recipient_email' => 'new@example.com'])->assertStatus(202)->assertJsonPath('customer_email', 'new@example.com');

        Notification::assertSentOnDemand(InvoiceIssued::class, fn (InvoiceIssued $n, array $c, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'new@example.com');
    }

    public function test_send_save_for_future_without_target_is_422(): void
    {
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => null, 'cleaning_object_id' => null, 'recurring_invoice_id' => null]);

        $this->api()->postJson('/api/invoices/'.$invoice->id.'/send', ['recipient_email' => 'x@example.com', 'save_for_future' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('save_for_future');
    }

    public function test_send_overdue_works_and_draft_is_422(): void
    {
        Notification::fake();
        $overdue = Invoice::factory()->overdue()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'c@example.com']);
        $draft = Invoice::factory()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'c@example.com']);
        $api = $this->api();

        $api->postJson('/api/invoices/'.$overdue->id.'/send')->assertStatus(202);
        $api->postJson('/api/invoices/'.$draft->id.'/send')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_send_is_throttled_at_ten_per_minute(): void
    {
        Notification::fake();
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id, 'customer_email' => 'c@example.com']);
        $api = $this->api();

        for ($i = 0; $i < 10; $i++) {
            $api->postJson('/api/invoices/'.$invoice->id.'/send')->assertStatus(202);
        }

        $api->postJson('/api/invoices/'.$invoice->id.'/send')->assertStatus(429);
    }
}
