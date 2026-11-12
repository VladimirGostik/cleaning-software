<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contracts\ContractUpsertData;
use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Data\Quotes\QuoteConvertToContractData;
use App\Data\Quotes\QuoteCreatePrefillData;
use App\Data\Quotes\QuoteListItemData;
use App\Data\Quotes\QuoteUpsertData;
use App\Data\RecurringInvoices\RecurringInvoiceItemData;
use App\Data\RecurringInvoices\RecurringInvoicePrefillData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\QuoteBillingPeriodEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Enums\RoundingModeEnum;
use App\Events\QuoteSent;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\User;
use App\Services\Documents\DocumentHtmlSanitizer;
use App\Services\Documents\DocumentTemplateCatalogue;
use App\Services\Documents\QuoteTextService;
use App\Support\CustomerParty;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class QuoteService
{
    public function __construct(
        private InvoiceService $invoices,
        private ContractService $contracts,
        private TemporaryUploadService $uploads,
        private DocumentItemService $items,
        private DatabaseManager $db,
        private QuoteTextService $text,
        private DocumentHtmlSanitizer $sanitizer,
        private DocumentTemplateCatalogue $catalogue,
    ) {}

    /**
     * Quotes on a client (its objects included) or on one object; null without `view quotes`.
     *
     * @return list<QuoteListItemData>|null
     */
    public function listFor(Client|CleaningObject $subject, User $actor): ?array
    {
        if (! $actor->can(PermissionEnum::ViewQuotes->value)) {
            return null;
        }

        $column = $subject instanceof Client ? 'client_id' : 'cleaning_object_id';

        return array_values(Quote::query()
            ->with(['client:id,name', 'cleaningObject:id,name', 'document.media'])
            ->where($column, $subject->id)
            ->latest('issue_date')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Quote $quote) => QuoteListItemData::fromModel($quote))
            ->all());
    }

    /** Drops ids the actor cannot reach; an object brings its own client. */
    public function createPrefill(?string $kind, ?string $clientId, ?string $objectId, User $actor): QuoteCreatePrefillData
    {
        $object = $objectId !== null && Str::isUuid($objectId)
            ? CleaningObject::query()->visibleTo($actor)->find($objectId)
            : null;

        $client = $object === null && $clientId !== null && Str::isUuid($clientId)
            ? Client::query()->find($clientId)
            : null;

        return new QuoteCreatePrefillData(
            kind: $kind !== null ? QuoteKindEnum::tryFrom($kind) : null,
            client_id: $object->client_id ?? $client?->id,
            cleaning_object_id: $object?->id,
        );
    }

    /**
     * @return LengthAwarePaginator<int, QuoteListItemData>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Quote::query())
            ->allowedFilters(
                AllowedFilter::callbackClean('search', function (Builder $query, mixed $value): void {
                    if (blank($value) || ! is_scalar($value)) {
                        return;
                    }

                    $like = '%'.Filters::escapeLikeValue((string) $value).'%';
                    $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

                    $query->where(function (Builder $q) use ($like, $operator): void {
                        $q->where('number', $operator, $like)
                            ->orWhere('subject', $operator, $like)
                            ->orWhere('customer_name', $operator, $like)
                            ->orWhereHas('client', fn (Builder $c) => $c->where('name', $operator, $like));
                    });
                }),
                AllowedFilter::dynamic('status'),
                AllowedFilter::dynamic('kind'),
                AllowedFilter::dynamic('client_id')->uuid(),
                AllowedFilter::dynamic('number'),
                AllowedFilter::dynamic('issue_date')->date(),
                AllowedFilter::dynamic('valid_until')->date(),
                AllowedFilter::dynamic('total')->numeric(),
                AllowedFilter::dynamic('created_at')->date(),
            )
            ->allowedSorts('number', 'status', 'kind', 'issue_date', 'valid_until', 'total', 'created_at')
            ->defaultSort('-created_at')
            ->with(['client:id,name', 'cleaningObject:id,name', 'document.media'])
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (Quote $quote) => QuoteListItemData::fromModel($quote));
    }

    public function create(QuoteUpsertData $data, ?User $actor, string $sessionId): Quote
    {
        return $this->db->transaction(function () use ($data, $actor, $sessionId): Quote {
            $tenant = Tenant::query()->findOrFail(current_tenant_id());

            $attributes = $this->buildAttributes($data, $tenant);
            $attributes['status'] = QuoteStatusEnum::Draft;

            $quote = Quote::create($attributes);

            if ($data->kind === QuoteKindEnum::Itemized) {
                $this->items->replacePriced($quote->items(), $data->items, (bool) $tenant->is_vat_payer);
                $quote->update($this->billingTotals($quote));
            }

            $this->syncDocument($quote, $data, $actor, $sessionId);

            return $quote->load('items');
        });
    }

    public function update(Quote $quote, QuoteUpsertData $data, ?User $actor, string $sessionId): Quote
    {
        if (! $quote->isEditable()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_not_editable')]]);
        }

        return $this->db->transaction(function () use ($quote, $data, $actor, $sessionId): Quote {
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($quote->tenant_id);

            $quote->update([
                ...$this->buildAttributes($data, $tenant),
                ...($quote->status === QuoteStatusEnum::Sent ? ['status' => QuoteStatusEnum::Draft, 'sent_at' => null] : []),
            ]);

            if ($data->kind === QuoteKindEnum::Itemized) {
                $this->items->replacePriced($quote->items(), $data->items, (bool) $quote->is_vat_payer);
                $quote->update($this->billingTotals($quote));
            }

            $this->syncDocument($quote, $data, $actor, $sessionId);

            return $quote->load('items');
        });
    }

    /** Unsaved quote with priced items for the previews; nothing is written. */
    public function previewModel(QuoteUpsertData $data, ?Quote $existing = null): Quote
    {
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($existing->tenant_id ?? current_tenant_id());

        $quote = new Quote([
            ...$this->buildAttributes($data, $tenant),
            'tenant_id' => $tenant->id,
            'status' => QuoteStatusEnum::Draft,
        ]);

        $quote->setRelation('items', new EloquentCollection(array_map(
            static fn (array $row): QuoteItem => new QuoteItem($row),
            $this->items->pricedRows($data->items, (bool) $tenant->is_vat_payer),
        )));
        $quote->forceFill($this->totalsFor($quote));

        return $quote;
    }

    public function send(Quote $quote): Quote
    {
        $this->guardLifecycleTransition($quote, QuoteStatusEnum::Sent);

        $missing = $this->text->missingTokens($quote);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'status' => [__('app.quote_unresolved_tokens', ['tokens' => $this->catalogue->labelsOf($missing)])],
            ]);
        }

        return $this->db->transaction(function () use ($quote): Quote {
            $resolved = $this->sanitizer->sanitize($this->text->printBody($quote));
            $document = $quote->textDocument() ?? $this->defaultDocument($quote);

            $document->update(['draft_body' => $document->body, 'body' => $resolved]);

            $quote->update(['status' => QuoteStatusEnum::Sent, 'sent_at' => now()]);

            QuoteSent::dispatch($quote->tenant_id, $quote->id);

            return $quote;
        });
    }

    public function accept(Quote $quote): Quote
    {
        $this->guardLifecycleTransition($quote, QuoteStatusEnum::Accepted);

        $quote->update(['status' => QuoteStatusEnum::Accepted, 'accepted_at' => now()]);

        return $quote;
    }

    public function reject(Quote $quote): Quote
    {
        $this->guardLifecycleTransition($quote, QuoteStatusEnum::Rejected);

        $quote->update(['status' => QuoteStatusEnum::Rejected, 'rejected_at' => now()]);

        return $quote;
    }

    public function attachClient(Quote $quote, string $clientId, ?string $objectId): Quote
    {
        if ($quote->client_id !== null) {
            throw ValidationException::withMessages(['client_id' => [__('app.quote_already_has_client')]]);
        }

        $quote->update([
            'client_id' => $clientId,
            'cleaning_object_id' => $objectId,
            ...(new CustomerParty)->toQuoteAttributes(),
        ]);

        return $quote;
    }

    public function duplicate(Quote $quote): Quote
    {
        return $this->db->transaction(function () use ($quote): Quote {
            $quote->loadMissing('items');

            $configuredValidityDays = config('quotes.default_validity_days', 30);
            $validityDays = is_numeric($configuredValidityDays) ? (int) $configuredValidityDays : 30;

            $newQuote = $quote->replicate(['number', 'sent_at', 'accepted_at', 'rejected_at', 'deleted_at'])
                ->withoutRelations()
                ->fill([
                    'status' => QuoteStatusEnum::Draft,
                    'issue_date' => now()->toDateString(),
                    'valid_until' => now()->addDays($validityDays)->toDateString(),
                ]);
            $newQuote->save();

            if ($quote->kind === QuoteKindEnum::Itemized) {
                foreach ($quote->items as $item) {
                    $newQuote->items()->save($item->replicate());
                }
            } else {
                $this->copyUploadedFile($quote, $newQuote);
            }

            $this->copyTextDocument($quote, $newQuote);

            return $newQuote->load('items');
        });
    }

    public function delete(Quote $quote): void
    {
        if (! $quote->isDeletable()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_not_editable')]]);
        }

        $this->db->transaction(function () use ($quote): void {
            $quote->document?->delete();
            $quote->delete();
        });
    }

    public function convertToInvoice(Quote $quote): Invoice
    {
        $this->guardConvertible($quote);

        $oneOffItems = $quote->items->where('billing_period', QuoteBillingPeriodEnum::OneOff);

        if ($oneOffItems->isEmpty()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_has_no_one_off_items')]]);
        }

        return $this->db->transaction(function () use ($quote, $oneOffItems): Invoice {

            $today = now()->toDateString();
            $defaultDueDays = config('invoicing.default_due_days', 14);
            $dueDate = now()->addDays(is_numeric($defaultDueDays) ? (int) $defaultDueDays : 14)->toDateString();

            $defaults = TenantInterface::query()->where('tenant_id', $quote->tenant_id)->first();

            $items = [];
            foreach ($oneOffItems as $item) {
                $items[] = InvoiceItemData::from([
                    'id' => null,
                    'description' => $this->itemDescription($item),
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => (float) $item->unit_price,
                    'discount_percent' => (float) $item->discount_percent,
                    'vat_rate' => (float) $item->vat_rate,
                    'line_base' => null,
                    'line_vat' => null,
                    'line_total' => null,
                ]);
            }

            $upsertData = InvoiceUpsertData::from([
                'client_id' => $quote->client_id,
                'cleaning_object_id' => $quote->cleaning_object_id,
                'type' => InvoiceTypeEnum::OneOff->value,
                'issue_date' => $today,
                'delivery_date' => $today,
                'due_date' => $dueDate,
                'period_from' => null,
                'period_to' => null,
                ...$this->quoteParty($quote)->toEnteredAttributes(),
                'note' => $quote->note,
                'items' => $items,
                'constant_symbol' => null,
                'specific_symbol' => null,
                'header_text' => $defaults?->default_header_text,
                'footer_text' => $defaults?->default_footer_text,
                'deposit' => 0,
                'payment_type' => PaymentTypeEnum::Transfer->value,
                'currency' => $quote->currency->value,
                'rounding_mode' => RoundingModeEnum::None->value,
            ]);

            $invoice = $this->invoices->create($upsertData);
            $invoice->update(['quote_id' => $quote->id]);

            return $invoice;
        });
    }

    public function recurringInvoicePrefill(Quote $quote, QuoteBillingPeriodEnum $period): RecurringInvoicePrefillData
    {
        $this->guardConvertible($quote);

        $frequency = $period->recurringFrequency();
        $items = $quote->items->where('billing_period', $period);

        if ($frequency === null || $items->isEmpty()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_has_no_items_for_period')]]);
        }

        return RecurringInvoicePrefillData::from([
            'quote_id' => $quote->id,
            'client_id' => $quote->client_id,
            'cleaning_object_id' => $quote->cleaning_object_id,
            'name' => __('app.quote_recurring_invoice_name', [
                'name' => $quote->subject ?? $quote->number ?? __('app.quote_pdf_title'),
                'period' => $period->label(),
            ]),
            'frequency' => $frequency,
            ...$this->quoteParty($quote)->toQuoteAttributes(),
            'currency' => $quote->currency,
            'note' => $quote->note,
            'items' => $items->map(fn (QuoteItem $item) => new RecurringInvoiceItemData(
                description: $this->itemDescription($item),
                quantity: (float) $item->quantity,
                unit: $item->unit,
                unit_price: (float) $item->unit_price,
                discount_percent: (float) $item->discount_percent,
                vat_rate: (float) $item->vat_rate,
            ))->values()->all(),
        ]);
    }

    public function convertToContract(Quote $quote, QuoteConvertToContractData $data, User $actor): Contract
    {
        if ($quote->isDocument()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_document_not_convertible')]]);
        }

        if (! $quote->canBeConverted()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_not_acceptable_for_conversion')]]);
        }

        if ($quote->client_id === null) {
            throw ValidationException::withMessages(['client_id' => [__('app.quote_client_required_for_contract')]]);
        }

        if ($quote->cleaning_object_id === null) {
            throw ValidationException::withMessages(['cleaning_object_id' => [__('app.quote_object_required_for_contract')]]);
        }

        $template = $data->document_template_id !== null
            ? DocumentTemplate::query()->findOrFail($data->document_template_id)
            : null;

        $upsert = ContractUpsertData::from([
            'title' => $quote->subject ?? __('app.contract_default_title_from_quote'),
            'number' => $quote->number,
            'category' => ContractCategoryEnum::ServiceAgreement->value,
            'term_type' => ContractTermTypeEnum::Indefinite->value,
            'contractable_type' => ContractableTypeEnum::CleaningObject->value,
            'contractable_id' => $quote->cleaning_object_id,
            'document_template_id' => $template?->id,
            'body' => $template !== null ? $template->body : $this->catalogue->skeleton(DocumentTypeEnum::ServiceAgreement, true),
            'valid_from' => now()->toDateString(),
            'end_date' => null,
            'notes' => $quote->note,
            'employment' => null,
            'show_header' => $template->show_header ?? true,
            'show_footer' => $template->show_footer ?? true,
        ]);

        return $this->contracts->create($upsert, $actor, $quote);
    }

    private function guardConvertible(Quote $quote): void
    {
        if ($quote->isDocument()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_document_not_convertible')]]);
        }

        if (! $quote->canBeConverted()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_not_acceptable_for_conversion')]]);
        }

        $quote->loadMissing('items');
    }

    private function itemDescription(QuoteItem $item): string
    {
        $description = $item->frequency !== null
            ? "{$item->description} ({$item->frequency->label()})"
            : $item->description;

        if ($item->note !== null && $item->note !== '') {
            $description .= " — {$item->note}";
        }

        return mb_strlen($description) > 255 ? mb_substr($description, 0, 254).'…' : $description;
    }

    private function syncDocument(Quote $quote, QuoteUpsertData $data, ?User $actor, string $sessionId): void
    {
        $quote->unsetRelation('document');

        if ($data->kind === QuoteKindEnum::Document) {
            if ($data->document_uuid !== null) {
                $document = $this->quoteDocument($quote, null);
                $this->uploads->moveToModel($document, 'file', $data->document_uuid, $actor, $sessionId);
            }

            return;
        }

        $body = $this->text->explicitBody($data);

        $title = $quote->subject ?? $quote->number ?? __('app.quote_pdf_title');

        if ($body === null) {
            $existing = $quote->document()->first();

            if ($existing?->body === null) {
                $this->defaultDocument($quote);

                return;
            }

            $existing->update(['title' => $title, 'body' => $existing->draft_body ?? $existing->body, 'draft_body' => null]);

            return;
        }

        $document = $this->quoteDocument($quote, $body);
        $document->update([
            'document_template_id' => $data->document_template_id,
            'title' => $title,
            'show_header' => $data->document_show_header,
            'show_footer' => $data->document_show_footer,
        ]);
    }

    private function defaultDocument(Quote $quote): Document
    {
        $template = $this->text->defaultTemplate($quote->tenant_id);
        $document = $this->quoteDocument($quote, $this->sanitizer->sanitize($template->body ?? $this->catalogue->skeleton(DocumentTypeEnum::Quote)));
        $document->update([
            'document_template_id' => $template?->id,
            'show_header' => $template->show_header ?? true,
            'show_footer' => $template->show_footer ?? true,
        ]);

        return $document;
    }

    private function quoteDocument(Quote $quote, ?string $body): Document
    {
        $document = $quote->document()->first() ?? new Document([
            'tenant_id' => $quote->tenant_id,
            'documentable_type' => $quote->getMorphClass(),
            'documentable_id' => $quote->id,
            'type' => DocumentTypeEnum::Quote,
            'title' => $quote->subject ?? $quote->number ?? __('app.quote_pdf_title'),
        ]);

        if ($body !== null) {
            $document->body = $body;
            $document->draft_body = null;
        }

        $document->save();

        return $document;
    }

    private function copyUploadedFile(Quote $source, Quote $target): void
    {
        $media = $source->document?->getFirstMedia('file');

        if ($media === null) {
            return;
        }

        $media->copy($this->quoteDocument($target, null), 'file');
    }

    private function copyTextDocument(Quote $source, Quote $target): void
    {
        if ($source->kind !== QuoteKindEnum::Itemized) {
            return;
        }

        $document = $source->document;

        if ($document === null || $document->body === null) {
            return;
        }

        $body = $source->status === QuoteStatusEnum::Draft
            ? $document->body
            : ($document->document_template_id !== null ? DocumentTemplate::query()->find($document->document_template_id)?->body : null);

        if ($body === null) {
            return;
        }

        $copy = $this->quoteDocument($target, $body);
        $copy->update([
            'document_template_id' => $document->document_template_id,
            'show_header' => $document->show_header,
            'show_footer' => $document->show_footer,
        ]);
    }

    private function guardLifecycleTransition(Quote $quote, QuoteStatusEnum $to): void
    {
        if (! $quote->status->canTransitionTo($to)) {
            throw ValidationException::withMessages(['status' => [__('app.quote_invalid_transition')]]);
        }

        if ($quote->isDocument()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_document_no_lifecycle')]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function billingTotals(Quote $quote): array
    {
        $quote->load('items');

        return $this->totalsFor($quote);
    }

    /**
     * @return array<string, mixed>
     */
    private function totalsFor(Quote $quote): array
    {
        $totals = $this->items->totals($quote->items, $quote->is_vat_payer);

        $periodTotals = [];

        foreach (QuoteBillingPeriodEnum::cases() as $period) {
            $periodItems = $quote->items->where('billing_period', $period);

            if ($periodItems->isEmpty()) {
                continue;
            }

            $group = $this->items->totals($periodItems, $quote->is_vat_payer);
            $periodTotals[] = [
                'billing_period' => $period->value,
                'subtotal' => number_format($group['subtotal'], 2, '.', ''),
                'vat_amount' => number_format($group['vat_amount'], 2, '.', ''),
                'total' => number_format($group['total'], 2, '.', ''),
            ];
        }

        return [
            'subtotal' => $totals['subtotal'],
            'vat_amount' => $totals['vat_amount'],
            'total' => $totals['total'],
            'vat_breakdown' => $totals['vat_breakdown'],
            'period_totals' => $periodTotals,
        ];
    }

    private function quoteParty(Quote $quote): CustomerParty
    {
        return $quote->client_id === null ? CustomerParty::fromQuote($quote) : new CustomerParty;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributes(QuoteUpsertData $data, Tenant $tenant): array
    {
        $hasClient = $data->client_id !== null;

        return [
            'client_id' => $data->client_id,
            'cleaning_object_id' => $data->cleaning_object_id,
            'kind' => $data->kind,
            'number' => $data->number,
            'subject' => $data->subject,
            'issue_date' => $data->issue_date,
            'valid_until' => $data->valid_until,
            'is_vat_payer' => $tenant->is_vat_payer,
            'vat_rate' => $tenant->is_vat_payer ? $tenant->vat_rate : null,
            'currency' => $data->currency,
            'note' => $data->note,
            'included_text' => $data->included_text,
            'show_totals' => $data->show_totals,
            'show_vat_note' => $data->show_vat_note,
            ...($hasClient ? new CustomerParty : CustomerParty::fromQuote($data))->toQuoteAttributes(),
        ];
    }
}
