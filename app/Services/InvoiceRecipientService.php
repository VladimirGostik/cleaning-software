<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invoices\InvoiceBuyerData;
use App\Data\Invoices\InvoiceBuyerPreviewData;
use App\Data\Invoices\InvoiceBuyerPreviewResultData;
use App\Data\Invoices\InvoiceSendData;
use App\Data\Invoices\InvoiceSendDraftData;
use App\Data\Invoices\InvoiceSendOptionsData;
use App\Enums\InvoiceEmailSaveTargetEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Documents\DocumentLocale;
use App\Support\CustomerParty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class InvoiceRecipientService
{
    public function __construct(private InvoiceService $invoices, private PlaceholderResolverService $placeholders, private DocumentLocale $locale) {}

    public function previewBuyer(InvoiceBuyerPreviewData $data, User $actor): InvoiceBuyerPreviewResultData
    {
        $client = Client::query()->findOrFail($data->client_id);
        $object = $data->cleaning_object_id !== null
            ? CleaningObject::query()->findOrFail($data->cleaning_object_id)
            : null;

        return new InvoiceBuyerPreviewResultData(
            buyer: InvoiceBuyerData::fromParty(CustomerParty::forSubject($object, $client) ?? new CustomerParty),
            email_options: $this->emailOptions($object, $client, $actor),
        );
    }

    public function sendOptions(Invoice $invoice, User $actor): ?InvoiceSendOptionsData
    {
        if (! $invoice->canBeSent()
            || $invoice->customer_email !== null
            || ! Gate::forUser($actor)->allows('send', $invoice)) {
            return null;
        }

        return new InvoiceSendOptionsData(
            email_options: $this->emailOptions($invoice->cleaningObject, $invoice->client, $actor),
            save_target: $this->saveTarget($invoice, $actor),
        );
    }

    public function send(Invoice $invoice, InvoiceSendData $data, User $actor): void
    {
        if ($invoice->customer_email !== null) {
            $this->invoices->send($invoice, $data->subject, $data->body);

            return;
        }

        if (! $invoice->canBeSent()) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_not_issued_to_send')]]);
        }

        if (blank($data->recipient_email)) {
            throw ValidationException::withMessages(['recipient_email' => [__('app.invoice_recipient_required')]]);
        }

        $target = $data->save_for_future ? $this->saveTarget($invoice, $actor) : null;

        if ($data->save_for_future && $target === null) {
            throw ValidationException::withMessages(['save_for_future' => [__('app.invoice_email_save_not_allowed')]]);
        }

        DB::transaction(function () use ($invoice, $data, $target): void {
            $invoice->update(['customer_email' => $data->recipient_email]);

            match ($target) {
                InvoiceEmailSaveTargetEnum::RecurringInvoice => $invoice->recurringInvoice?->update(['customer_email' => $data->recipient_email]),
                InvoiceEmailSaveTargetEnum::Object => $invoice->cleaningObject?->update(['billing_email' => $data->recipient_email]),
                null => null,
            };
        });

        $this->invoices->send($invoice, $data->subject, $data->body);
    }

    public function sendDraft(Invoice $invoice, User $actor): InvoiceSendDraftData
    {
        if (! $invoice->canBeSent()) {
            throw ValidationException::withMessages(['status' => [__('app.invoice_not_issued_to_send')]]);
        }

        $invoice->loadMissing(['client', 'cleaningObject', 'recurringInvoice']);

        $content = $this->locale->run(fn (): array => $this->placeholders->invoiceMailContent($invoice));

        $options = $invoice->customer_email === null ? $this->sendOptions($invoice, $actor) : null;

        return new InvoiceSendDraftData(
            recipient_email: $invoice->customer_email,
            email_options: $options->email_options ?? [],
            save_target: $options?->save_target,
            subject: $content['subject'],
            body: $content['body'],
            attachment_name: $invoice->pdfFilenameBase().'.pdf',
        );
    }

    /** @return list<string> */
    private function emailOptions(?CleaningObject $object, ?Client $client, User $actor): array
    {
        $gate = Gate::forUser($actor);
        $emails = [];

        if ($object !== null && $gate->allows('viewBilling', CleaningObject::class)) {
            $emails[] = $object->billing_email;
        }

        if ($object !== null && $gate->allows('viewContacts', CleaningObject::class)) {
            array_push($emails, ...$object->contacts()->pluck('email')->all());
        }

        if ($client !== null && $gate->allows('view', $client)) {
            $contacts = $client->contacts()->orderByDesc('is_primary')->orderBy('name')->pluck('email')->all();
            array_push($emails, ...$contacts);
        }

        $options = [];

        foreach ($emails as $email) {
            if (! is_string($email) || trim($email) === '') {
                continue;
            }

            $options[mb_strtolower(trim($email))] ??= trim($email);
        }

        return array_values($options);
    }

    private function saveTarget(Invoice $invoice, User $actor): ?InvoiceEmailSaveTargetEnum
    {
        $gate = Gate::forUser($actor);
        $template = $invoice->recurringInvoice;

        if ($template !== null
            && in_array($template->status, [RecurringInvoiceStatusEnum::Active, RecurringInvoiceStatusEnum::Paused], true)
            && $gate->allows('update', $template)) {
            return InvoiceEmailSaveTargetEnum::RecurringInvoice;
        }

        $object = $invoice->cleaningObject;

        if ($object !== null
            && ! $object->trashed()
            && $gate->allows('update', $object)
            && $gate->allows('viewBilling', CleaningObject::class)) {
            return InvoiceEmailSaveTargetEnum::Object;
        }

        return null;
    }
}
