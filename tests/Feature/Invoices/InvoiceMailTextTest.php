<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\RendersInvoicePdf;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Notifications\InvoiceIssued;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Mockery\Expectation;
use Tests\TestCase;

final class InvoiceMailTextTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $interface
     * @param  array<string, mixed>  $invoiceAttributes
     */
    private function invoiceWithTexts(array $interface, array $invoiceAttributes = []): Invoice
    {
        $tenant = Tenant::factory()->create();
        TenantInterface::query()->updateOrCreate(['tenant_id' => $tenant->id], $interface);

        return Invoice::factory()->issued()->create(array_merge([
            'tenant_id' => $tenant->id,
            'customer_email' => 'client@example.com',
            'customer_name' => 'Acme s.r.o.',
        ], $invoiceAttributes));
    }

    private function mail(Invoice $invoice): MailMessage
    {
        /** @var Expectation $expectation */
        $expectation = $this->mock(RendersInvoicePdf::class)->shouldReceive('render');
        $expectation->andReturn('%PDF-1.4 fake');

        return (new InvoiceIssued($invoice->id))->toMail(new AnonymousNotifiable);
    }

    private function html(MailMessage $message): string
    {
        /** @var array<string, mixed> $data */
        $data = $message->viewData;

        return view('mail.invoice', $data)->render();
    }

    public function test_custom_subject_resolves_placeholders(): void
    {
        $invoice = $this->invoiceWithTexts(['invoice_mail_subject' => 'Faktúra {{invoice.number}} – {{customer.name}}']);

        $message = $this->mail($invoice);

        $this->assertSame("Faktúra {$invoice->number} – Acme s.r.o.", $message->subject);
    }

    public function test_empty_fields_fall_back_to_lang_defaults(): void
    {
        $invoice = $this->invoiceWithTexts(['invoice_mail_subject' => null, 'invoice_mail_body' => null]);

        $message = $this->mail($invoice);
        $html = $this->html($message);

        $this->assertSame(__('app.invoice_mail_subject', ['number' => $invoice->number]), $message->subject);
        $this->assertStringContainsString(__('app.invoice_mail_body'), $html);
        $this->assertStringContainsString('Acme s.r.o.', $html);
    }

    public function test_whitespace_only_fields_fall_back_to_defaults(): void
    {
        $invoice = $this->invoiceWithTexts(['invoice_mail_subject' => '   ', 'invoice_mail_body' => "\n "]);

        $message = $this->mail($invoice);

        $this->assertSame(__('app.invoice_mail_subject', ['number' => $invoice->number]), $message->subject);
        $this->assertStringContainsString(__('app.invoice_mail_body'), $this->html($message));
    }

    public function test_body_is_escaped_with_line_breaks(): void
    {
        $invoice = $this->invoiceWithTexts([
            'invoice_mail_body' => "Dobrý deň {{customer.name}}\n<script>alert(1)</script>",
        ]);

        $html = $this->html($this->mail($invoice));

        $this->assertStringContainsString('Dobrý deň Acme s.r.o.<br />', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString(__('app.invoice_mail_body'), $html);
        $this->assertStringContainsString((string) $invoice->number, $html);
    }

    public function test_unknown_token_stays_verbatim(): void
    {
        $invoice = $this->invoiceWithTexts(['invoice_mail_subject' => 'X {{foo.bar}} {{invoice.number}}']);

        $this->assertSame("X {{foo.bar}} {$invoice->number}", $this->mail($invoice)->subject);
    }

    public function test_period_empty_without_period_and_formatted_with_it(): void
    {
        $without = $this->invoiceWithTexts(
            ['invoice_mail_subject' => '[{{invoice.period}}]'],
            ['period_from' => null, 'period_to' => null],
        );
        $this->assertSame('[]', $this->mail($without)->subject);

        $with = $this->invoiceWithTexts(
            ['invoice_mail_subject' => '[{{invoice.period}}]'],
            ['period_from' => '2026-09-01', 'period_to' => '2026-09-30'],
        );
        $this->assertSame('[01.09.2026 – 30.09.2026]', $this->mail($with)->subject);
    }

    public function test_missing_number_resolves_to_empty_string(): void
    {
        $invoice = $this->invoiceWithTexts(['invoice_mail_subject' => 'N:{{invoice.number}}'], ['number' => null]);

        $this->assertSame('N:', $this->mail($invoice)->subject);
    }

    public function test_money_tokens_use_currency_format_with_nbsp(): void
    {
        $invoice = $this->invoiceWithTexts(['invoice_mail_subject' => '{{invoice.total}}|{{invoice.balance_due}}']);

        $expected = $invoice->currency->format((float) $invoice->total).'|'.$invoice->currency->format($invoice->balance_due);

        $this->assertSame($expected, $this->mail($invoice)->subject);
        $this->assertStringContainsString("\u{00A0}", (string) $this->mail($invoice)->subject);
    }

    public function test_resolution_ignores_current_tenant_and_uses_invoice_tenant(): void
    {
        $invoice = $this->invoiceWithTexts(['invoice_mail_subject' => 'Own {{customer.name}}']);
        $other = Tenant::factory()->create();
        TenantInterface::query()->updateOrCreate(['tenant_id' => $other->id], ['invoice_mail_subject' => 'Other']);
        $this->bindTenant($other);

        $this->assertSame('Own Acme s.r.o.', $this->mail($invoice)->subject);
    }

    public function test_tenant_without_interface_row_uses_defaults(): void
    {
        $tenant = Tenant::factory()->create();
        TenantInterface::query()->where('tenant_id', $tenant->id)->delete();
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'customer_email' => 'c@example.com']);

        $this->assertSame(__('app.invoice_mail_subject', ['number' => $invoice->number]), $this->mail($invoice)->subject);
    }

    public function test_send_path_dispatches_same_notification_used_for_custom_text(): void
    {
        Notification::fake();
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $tenant->id, 'customer_email' => 'c@example.com']);

        app(InvoiceService::class)->send($invoice);

        Notification::assertSentOnDemand(InvoiceIssued::class);
    }
}
