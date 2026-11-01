<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Contracts\RendersInvoicePdf;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Notifications\InvoiceIssued;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Mockery\Expectation;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class InvoiceMailLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_mail_embeds_the_logo_inline_without_a_remote_url(): void
    {
        config(['app.url' => 'http://unreachable.invalid']);
        /** @var Expectation $expectation */
        $expectation = $this->mock(RendersInvoicePdf::class)->shouldReceive('render');
        $expectation->andReturn('%PDF-1.4 fake');
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => Tenant::factory()->create()->id, 'customer_email' => 'client@example.com']);

        Notification::route('mail', 'client@example.com')->notifyNow(new InvoiceIssued($invoice->id));

        /** @var ArrayTransport $transport */
        $transport = Mail::mailer('array')->getSymfonyTransport();
        /** @var SentMessage $sent */
        $sent = $transport->messages()->last();
        /** @var Email $email */
        $email = $sent->getOriginalMessage();
        $html = (string) $email->getHtmlBody();

        $inline = collect($email->getAttachments())->filter(fn ($part) => $part->getPreparedHeaders()->getHeaderBody('Content-Disposition') === 'inline');

        $this->assertCount(1, $inline);
        $this->assertStringContainsString('src="cid:', $html);
        $this->assertStringNotContainsString('http://unreachable.invalid', $html);
        $this->assertStringNotContainsString('kegos-logo-email.png"', $html);
    }

    public function test_preview_without_message_variable_falls_back_to_the_public_url(): void
    {
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $html = view('mail.invoice', ['invoice' => $invoice, 'bodyText' => null, 'mailSubject' => 'x'])->render();

        $this->assertStringContainsString('images/kegos-logo-email.png', $html);
    }
}
