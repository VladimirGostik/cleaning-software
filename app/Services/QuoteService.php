<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contracts\ContractUpsertData;
use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Data\Quotes\QuoteConvertToContractData;
use App\Data\Quotes\QuoteItemData;
use App\Data\Quotes\QuoteListItemData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Enums\RoundingModeEnum;
use App\Events\QuoteSent;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\User;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class QuoteService
{
    public function __construct(
        private InvoiceService $invoices,
        private ContractService $contracts,
        private TemporaryUploadService $uploads,
        private DocumentTotalsCalculator $totals,
        private DatabaseManager $db,
    ) {}

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
            ->with(['client:id,name', 'cleaningObject:id,name', 'media'])
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
                $this->syncItems($quote, $data->items, $tenant->id, (bool) $tenant->is_vat_payer);
                $quote->update($this->computeTotals($quote));
            } elseif ($data->document_uuid !== null) {
                $this->uploads->moveToModel($quote, 'document', $data->document_uuid, $actor, $sessionId);
            }

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

            $quote->update($this->buildAttributes($data, $tenant));

            if ($data->kind === QuoteKindEnum::Itemized) {
                $this->syncItems($quote, $data->items, $tenant->id, (bool) $quote->is_vat_payer);
                $quote->update($this->computeTotals($quote));
            } elseif ($data->document_uuid !== null) {
                $this->uploads->moveToModel($quote, 'document', $data->document_uuid, $actor, $sessionId);
            }

            return $quote->load('items');
        });
    }

    public function send(Quote $quote): Quote
    {
        $this->guardLifecycleTransition($quote, QuoteStatusEnum::Sent);

        $quote->update(['status' => QuoteStatusEnum::Sent, 'sent_at' => now()]);

        QuoteSent::dispatch($quote->tenant_id, $quote->id);

        return $quote;
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
            'customer_name' => null,
            'customer_email' => null,
            'customer_street' => null,
            'customer_city' => null,
            'customer_postal_code' => null,
        ]);

        return $quote;
    }

    public function duplicate(Quote $quote): Quote
    {
        return $this->db->transaction(function () use ($quote): Quote {
            $quote->loadMissing('items');

            $configuredValidityDays = config('quotes.default_validity_days', 30);
            $validityDays = is_numeric($configuredValidityDays) ? (int) $configuredValidityDays : 30;

            $newQuote = Quote::create([
                'tenant_id' => $quote->tenant_id,
                'client_id' => $quote->client_id,
                'cleaning_object_id' => $quote->cleaning_object_id,
                'status' => QuoteStatusEnum::Draft,
                'kind' => $quote->kind,
                'number' => null,
                'subject' => $quote->subject,
                'customer_name' => $quote->customer_name,
                'customer_email' => $quote->customer_email,
                'customer_street' => $quote->customer_street,
                'customer_city' => $quote->customer_city,
                'customer_postal_code' => $quote->customer_postal_code,
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays($validityDays)->toDateString(),
                'is_vat_payer' => $quote->is_vat_payer,
                'vat_rate' => $quote->vat_rate,
                'currency' => $quote->currency,
                'subtotal' => $quote->subtotal,
                'vat_amount' => $quote->vat_amount,
                'total' => $quote->total,
                'vat_breakdown' => $quote->vat_breakdown,
                'note' => $quote->note,
            ]);

            if ($quote->kind === QuoteKindEnum::Itemized) {
                /** @var QuoteItem $item */
                foreach ($quote->items as $item) {
                    QuoteItem::create([
                        'tenant_id' => $quote->tenant_id,
                        'quote_id' => $newQuote->id,
                        'description' => $item->description,
                        'frequency' => $item->frequency,
                        'note' => $item->note,
                        'quantity' => $item->quantity,
                        'unit' => $item->unit,
                        'unit_price' => $item->unit_price,
                        'discount_percent' => $item->discount_percent,
                        'vat_rate' => $item->vat_rate,
                        'line_base' => $item->line_base,
                        'line_vat' => $item->line_vat,
                        'line_total' => $item->line_total,
                        'position' => $item->position,
                    ]);
                }
            } else {
                $quote->getFirstMedia('document')?->copy($newQuote, 'document');
            }

            return $newQuote->load('items');
        });
    }

    public function delete(Quote $quote): void
    {
        if (! $quote->isEditable()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_not_editable')]]);
        }

        $this->db->transaction(function () use ($quote): void {
            $quote->delete();
        });
    }

    public function convertToInvoice(Quote $quote): Invoice
    {
        if ($quote->isDocument()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_document_not_convertible')]]);
        }

        if (! $quote->canBeConverted()) {
            throw ValidationException::withMessages(['status' => [__('app.quote_not_acceptable_for_conversion')]]);
        }

        return $this->db->transaction(function () use ($quote): Invoice {
            $quote->loadMissing('items');

            $today = now()->toDateString();
            $defaultDueDays = config('invoicing.default_due_days', 14);
            $dueDate = now()->addDays(is_numeric($defaultDueDays) ? (int) $defaultDueDays : 14)->toDateString();
            $isClientless = $quote->client_id === null;

            $items = [];
            /** @var QuoteItem $item */
            foreach ($quote->items as $item) {
                $description = $item->frequency !== null && $item->frequency !== ''
                    ? "{$item->description} ({$item->frequency})"
                    : $item->description;

                if ($item->note !== null && $item->note !== '') {
                    $description .= " — {$item->note}";
                }

                $items[] = InvoiceItemData::from([
                    'id' => null,
                    'description' => $description,
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
                'template' => null,
                'issue_date' => $today,
                'delivery_date' => $today,
                'due_date' => $dueDate,
                'period_from' => null,
                'period_to' => null,
                'customer_name' => $isClientless ? $quote->customer_name : null,
                'customer_representative' => null,
                'customer_ico' => null,
                'customer_dic' => null,
                'customer_vat_number' => null,
                'customer_street' => $isClientless ? $quote->customer_street : null,
                'customer_city' => $isClientless ? $quote->customer_city : null,
                'customer_postal_code' => $isClientless ? $quote->customer_postal_code : null,
                'customer_country' => null,
                'customer_email' => $isClientless ? $quote->customer_email : null,
                'note' => $quote->note,
                'items' => $items,
                'constant_symbol' => null,
                'specific_symbol' => null,
                'header_text' => null,
                'footer_text' => null,
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

    public function convertToContract(Quote $quote, QuoteConvertToContractData $data): Contract
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

        $template = $data->contract_template_id !== null
            ? ContractTemplate::query()->findOrFail($data->contract_template_id)
            : null;

        $upsert = ContractUpsertData::from([
            'title' => $quote->subject ?? __('app.contract_default_title_from_quote'),
            'number' => $quote->number,
            'category' => ContractCategoryEnum::ServiceAgreement->value,
            'term_type' => ContractTermTypeEnum::Indefinite->value,
            'contractable_type' => ContractableTypeEnum::CleaningObject->value,
            'contractable_id' => $quote->cleaning_object_id,
            'contract_template_id' => $template?->id,
            'body' => $template !== null ? $template->body : '{{quote.items}}',
            'valid_from' => now()->toDateString(),
            'end_date' => null,
            'notes' => $quote->note,
            'employment' => null,
        ]);

        return $this->contracts->create($upsert, $quote);
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
     * @param  array<QuoteItemData>  $items
     */
    private function syncItems(Quote $quote, array $items, string $tenantId, bool $isVatPayer): void
    {
        $quote->items()->delete();

        foreach ($items as $position => $itemData) {
            $line = $this->totals->line($itemData->quantity, $itemData->unit_price, $itemData->discount_percent, $itemData->vat_rate, $isVatPayer);

            QuoteItem::create([
                'tenant_id' => $tenantId,
                'quote_id' => $quote->id,
                'description' => $itemData->description,
                'frequency' => $itemData->frequency,
                'note' => $itemData->note,
                'quantity' => $itemData->quantity,
                'unit' => $itemData->unit,
                'unit_price' => $itemData->unit_price,
                'discount_percent' => $itemData->discount_percent,
                'vat_rate' => $itemData->vat_rate,
                'line_base' => $line['line_base'],
                'line_vat' => $line['line_vat'],
                'line_total' => $line['line_total'],
                'position' => $position,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function computeTotals(Quote $quote): array
    {
        $quote->loadMissing('items');

        $totals = $this->totals->totals(
            $quote->items->map(fn (QuoteItem $item) => $item->only(['vat_rate', 'line_base', 'line_vat', 'line_total']))->all(),
            $quote->is_vat_payer,
        );

        return [
            'subtotal' => $totals['subtotal'],
            'vat_amount' => $totals['vat_amount'],
            'total' => $totals['total'],
            'vat_breakdown' => $totals['vat_breakdown'],
        ];
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
            'customer_name' => $hasClient ? null : $data->customer_name,
            'customer_email' => $hasClient ? null : $data->customer_email,
            'customer_street' => $hasClient ? null : $data->customer_street,
            'customer_city' => $hasClient ? null : $data->customer_city,
            'customer_postal_code' => $hasClient ? null : $data->customer_postal_code,
        ];
    }
}
