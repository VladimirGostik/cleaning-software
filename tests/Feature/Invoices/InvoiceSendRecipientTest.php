<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Enums\InvoiceEmailSaveTargetEnum;
use App\Enums\PermissionEnum;
use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Role;
use App\Models\Tenant;
use App\Notifications\InvoiceIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class InvoiceSendRecipientTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Client $client;

    private CleaningObject $object;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->object = CleaningObject::factory()->create(['tenant_id' => $this->tenant->id, 'client_id' => $this->client->id]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function issued(array $overrides = []): Invoice
    {
        return Invoice::factory()->issued()->create($overrides + [
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cleaning_object_id' => $this->object->id,
            'customer_email' => null,
        ]);
    }

    private function template(string $status = 'active'): RecurringInvoice
    {
        return RecurringInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'cleaning_object_id' => $this->object->id,
            'status' => $status,
            'customer_email' => null,
        ]);
    }

    /** @param  list<PermissionEnum>  $permissions */
    private function revoke(array $permissions): void
    {
        $role = Role::inTenant($this->tenant->id)->where('name', 'Admin')->firstOrFail();

        foreach ($permissions as $permission) {
            $role->revokePermissionTo($permission->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function sentTo(string $email): void
    {
        Notification::assertSentOnDemand(
            InvoiceIssued::class,
            fn (InvoiceIssued $n, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === $email,
        );
    }

    public function test_send_options_for_issued_invoice_without_email(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);
        Contact::factory()->for($this->client, 'contactable')->create(['tenant_id' => $this->tenant->id, 'email' => 'klient@example.com', 'is_primary' => true]);
        $invoice = $this->issued();

        $this->get(route('invoices.show', $invoice))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('sendOptions.email_options', ['klient@example.com'])
                ->where('sendOptions.save_target', 'object'),
        );
    }

    public function test_send_options_are_null_when_not_applicable(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);

        $withEmail = $this->issued(['customer_email' => 'a@example.com']);
        $draft = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_email' => null]);

        $this->get(route('invoices.show', $withEmail))->assertInertia(fn (AssertableInertia $p) => $p->where('sendOptions', null));
        $this->get(route('invoices.show', $draft))->assertInertia(fn (AssertableInertia $p) => $p->where('sendOptions', null));

        $this->revoke([PermissionEnum::EditInvoices]);
        $this->get(route('invoices.show', $this->issued()))->assertInertia(fn (AssertableInertia $p) => $p->where('sendOptions', null));
    }

    public function test_save_target_is_recurring_invoice_for_active_template_else_object_else_null(): void
    {
        $this->actingAsTenantUser('Admin', $this->tenant);

        $fromTemplate = $this->issued(['recurring_invoice_id' => $this->template()->id]);
        $this->get(route('invoices.show', $fromTemplate))->assertInertia(fn (AssertableInertia $p) => $p->where('sendOptions.save_target', InvoiceEmailSaveTargetEnum::RecurringInvoice->value));

        $fromCancelled = $this->issued(['recurring_invoice_id' => $this->template('cancelled')->id]);
        $this->get(route('invoices.show', $fromCancelled))->assertInertia(fn (AssertableInertia $p) => $p->where('sendOptions.save_target', 'object'));

        $standalone = $this->issued(['client_id' => null, 'cleaning_object_id' => null]);
        $this->get(route('invoices.show', $standalone))->assertInertia(fn (AssertableInertia $p) => $p->where('sendOptions.save_target', null));
    }

    public function test_send_with_typed_address_stores_it_on_the_invoice_and_logs(): void
    {
        Notification::fake();
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->issued();

        $this->post(route('invoices.send', $invoice), ['recipient_email' => 'typed@example.com', 'save_for_future' => false])
            ->assertRedirect(route('invoices.show', $invoice));

        $this->sentTo('typed@example.com');
        $this->assertSame('typed@example.com', $invoice->refresh()->customer_email);
        $logged = Activity::query()->where('subject_id', $invoice->id)->get()
            ->contains(fn (Activity $a) => data_get($a->attributeChangesArray(), 'attributes.customer_email') === 'typed@example.com');
        $this->assertTrue($logged);
        $this->assertNull($this->object->refresh()->billing_email);
    }

    public function test_save_for_future_updates_the_recurring_template(): void
    {
        Notification::fake();
        $this->actingAsTenantUser('Admin', $this->tenant);
        $template = $this->template();
        $invoice = $this->issued(['recurring_invoice_id' => $template->id]);

        $this->post(route('invoices.send', $invoice), ['recipient_email' => 'saved@example.com', 'save_for_future' => true])->assertRedirect();

        $this->assertSame('saved@example.com', $template->refresh()->customer_email);
        $this->sentTo('saved@example.com');
    }

    public function test_save_for_future_updates_the_object_billing_email(): void
    {
        Notification::fake();
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->issued();

        $this->post(route('invoices.send', $invoice), ['recipient_email' => 'objekt@example.com', 'save_for_future' => true])->assertRedirect();

        $this->assertSame('objekt@example.com', $this->object->refresh()->billing_email);
    }

    public function test_save_for_future_without_target_is_rejected_and_nothing_is_written(): void
    {
        Notification::fake();
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->issued(['client_id' => null, 'cleaning_object_id' => null]);

        $this->post(route('invoices.send', $invoice), ['recipient_email' => 'x@example.com', 'save_for_future' => true])
            ->assertSessionHasErrors('save_for_future');

        Notification::assertNothingSent();
        $this->assertNull($invoice->refresh()->customer_email);
    }

    public function test_missing_or_invalid_recipient_is_rejected(): void
    {
        Notification::fake();
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->issued();

        $this->post(route('invoices.send', $invoice))->assertSessionHasErrors('recipient_email');
        $this->post(route('invoices.send', $invoice), ['recipient_email' => 'not-an-email'])->assertSessionHasErrors('recipient_email');

        Notification::assertNothingSent();
    }

    public function test_invoice_with_email_ignores_the_body(): void
    {
        Notification::fake();
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->issued(['customer_email' => 'own@example.com']);

        $this->post(route('invoices.send', $invoice), ['recipient_email' => 'other@example.com'])->assertRedirect();

        $this->sentTo('own@example.com');
        $this->assertSame('own@example.com', $invoice->refresh()->customer_email);
    }

    public function test_bulk_send_still_skips_invoices_without_email(): void
    {
        Notification::fake();
        $this->actingAsTenantUser('Admin', $this->tenant);
        $invoice = $this->issued();

        $this->post(route('invoices.bulk.send'), ['ids' => [$invoice->id]]);

        Notification::assertNothingSent();
    }
}
