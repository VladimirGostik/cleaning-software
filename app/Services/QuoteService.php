<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contracts\ContractUpsertData;
use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoiceUpsertData;
use App\Data\Quotes\QuoteIndexFilterData;
use App\Data\Quotes\QuoteItemData;
use App\Data\Quotes\QuoteUpsertData;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\QuoteStatusEnum;
use App\Enums\RoundingModeEnum;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Notifications\QuoteSent;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class QuoteService
{
    public function __construct(
        private QuoteNumberService $numbers,
        private InvoiceService $invoices,
        private ContractService $contracts,
        private DatabaseManager $db,
        private NotificationRecipientResolver $resolver,
    ) {}

    /**
     * @return LengthAwarePaginator<Quote>
     */
    public function paginate(QuoteIndexFilterData $filter): LengthAwarePaginator
    {
        return QueryBuilder::for(Quote::class)
            ->allowedFilters(
                AllowedFilter::scope('search'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('client_id'),
            )
            ->allowedSorts(
                AllowedSort::field('created_at'),
                AllowedSort::field('valid_until'),
                AllowedSort::field('issue_date'),
            )
            ->defaultSort('-created_at')
            ->with(['client:id,name', 'cleaningObject:id,name'])
            ->when($filter->valid_from, fn ($q, $v) => $q->whereDate('valid_until', '>=', $v))
            ->when($filter->valid_to, fn ($q, $v) => $q->whereDate('valid_until', '<=', $v))
            ->paginate($filter->per_page)
            ->appends(request()->query());
    }

    public function create(QuoteUpsertData $data): Quote
    {
        return $this->db->transaction(function () use ($data): Quote {
            $tenantId = app('current_tenant_id');
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);

            $issueDate = Carbon::parse($data->issue_date);
            $number = $this->numbers->next($tenant, $issueDate);

            $attributes = $this->buildAttributes($data, $tenant);

            $quote = Quote::create([
                ...$attributes,
                'status' => QuoteStatusEnum::Draft,
                'number' => $number,
            ]);

            $this->syncItems($quote, $data->items, $tenant->id, $tenant->is_vat_payer);

            $totals = $this->computeTotals($quote);
            $quote->update($totals);

            return $quote->load('items');
        });
    }

    public function update(Quote $quote, QuoteUpsertData $data): Quote
    {
        if (! $quote->isEditable()) {
            throw ValidationException::withMessages([
                'status' => [__('app.quotes.not_editable')],
            ]);
        }

        return $this->db->transaction(function () use ($quote, $data): Quote {
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($quote->tenant_id);

            $attributes = $this->buildAttributes($data, $tenant);
            $quote->update($attributes);

            $this->syncItems($quote, $data->items, $tenant->id, $quote->is_vat_payer);

            $totals = $this->computeTotals($quote);
            $quote->update($totals);

            return $quote->load('items');
        });
    }

    public function send(Quote $quote): Quote
    {
        if (! $quote->status->canTransitionTo(QuoteStatusEnum::Sent)) {
            throw ValidationException::withMessages([
                'status' => [__('app.quotes.invalid_transition')],
            ]);
        }

        $quote->update([
            'status' => QuoteStatusEnum::Sent,
            'sent_at' => now(),
        ]);

        $recipients = $this->resolver->usersWithPermission($quote->tenant_id, PermissionEnum::ViewQuotes);
        Notification::send($recipients, new QuoteSent($quote->tenant_id, $quote->id));

        return $quote;
    }

    public function accept(Quote $quote): Quote
    {
        if (! $quote->status->canTransitionTo(QuoteStatusEnum::Accepted)) {
            throw ValidationException::withMessages([
                'status' => [__('app.quotes.invalid_transition')],
            ]);
        }

        $quote->update([
            'status' => QuoteStatusEnum::Accepted,
            'accepted_at' => now(),
        ]);

        return $quote;
    }

    public function reject(Quote $quote): Quote
    {
        if (! $quote->status->canTransitionTo(QuoteStatusEnum::Rejected)) {
            throw ValidationException::withMessages([
                'status' => [__('app.quotes.invalid_transition')],
            ]);
        }

        $quote->update([
            'status' => QuoteStatusEnum::Rejected,
            'rejected_at' => now(),
        ]);

        return $quote;
    }

    public function duplicate(Quote $quote): Quote
    {
        return $this->db->transaction(function () use ($quote): Quote {
            $quote->loadMissing('items');

            $tenant = Tenant::withoutGlobalScopes()->findOrFail($quote->tenant_id);
            $issueDate = now();
            $number = $this->numbers->next($tenant, $issueDate);

            $newQuote = Quote::create([
                'client_id' => $quote->client_id,
                'cleaning_object_id' => $quote->cleaning_object_id,
                'status' => QuoteStatusEnum::Draft,
                'number' => $number,
                'subject' => $quote->subject,
                'issue_date' => $issueDate->toDateString(),
                'valid_until' => $issueDate->copy()->addDays(30)->toDateString(),
                'is_vat_payer' => $quote->is_vat_payer,
                'vat_rate' => $quote->vat_rate,
                'currency' => $quote->currency,
                'subtotal' => $quote->subtotal,
                'vat_amount' => $quote->vat_amount,
                'total' => $quote->total,
                'vat_breakdown' => $quote->vat_breakdown,
                'note' => $quote->note,
            ]);

            /** @var QuoteItem $item */
            foreach ($quote->items as $item) {
                QuoteItem::create([
                    'tenant_id' => $quote->tenant_id,
                    'quote_id' => $newQuote->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'frequency' => $item->frequency,
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

            return $newQuote->load('items');
        });
    }

    public function delete(Quote $quote): void
    {
        if (! $quote->isEditable()) {
            throw ValidationException::withMessages([
                'status' => [__('app.quotes.not_editable')],
            ]);
        }

        $quote->delete();
    }

    public function convertToInvoice(Quote $quote): Invoice
    {
        if (! $quote->canBeConverted()) {
            throw ValidationException::withMessages([
                'status' => [__('app.quotes.not_acceptable_for_conversion')],
            ]);
        }

        $quote->loadMissing('items');

        $items = $quote->items->map(fn (QuoteItem $item): InvoiceItemData => new InvoiceItemData(
            id: null,
            description: $item->name . ($item->frequency !== null ? ' (' . $item->frequency . ')' : ''),
            quantity: (float) $item->quantity,
            unit: $item->unit,
            unit_price: (float) $item->unit_price,
            discount_percent: (float) $item->discount_percent,
            vat_rate: (float) $item->vat_rate,
        ))->all();

        $invoiceData = new InvoiceUpsertData(
            client_id: $quote->client_id,
            cleaning_object_id: $quote->cleaning_object_id,
            type: InvoiceTypeEnum::OneOff,
            template: null,
            issue_date: now()->toDateString(),
            delivery_date: now()->toDateString(),
            due_date: now()->addDays(14)->toDateString(),
            period_from: null,
            period_to: null,
            customer_name: null,
            customer_representative: null,
            customer_ico: null,
            customer_dic: null,
            customer_vat_number: null,
            customer_street: null,
            customer_city: null,
            customer_postal_code: null,
            customer_country: null,
            customer_email: null,
            note: $quote->note,
            items: $items,
            constant_symbol: null,
            specific_symbol: null,
            header_text: null,
            footer_text: null,
            deposit: 0.0,
            payment_type: PaymentTypeEnum::Transfer,
            currency: $quote->currency,
            rounding_mode: RoundingModeEnum::None,
        );

        return $this->db->transaction(function () use ($quote, $invoiceData): Invoice {
            $invoice = $this->invoices->create($invoiceData);
            $invoice->update(['quote_id' => $quote->id]);

            return $invoice;
        });
    }

    public function convertToContract(Quote $quote): Contract
    {
        if (! $quote->canBeConverted()) {
            throw ValidationException::withMessages([
                'status' => [__('app.quotes.not_acceptable_for_conversion')],
            ]);
        }

        if ($quote->cleaning_object_id === null) {
            throw ValidationException::withMessages([
                'cleaning_object_id' => [__('app.quotes.object_required_for_contract')],
            ]);
        }

        $quote->loadMissing('items');

        $contractData = new ContractUpsertData(
            title: $quote->subject ?? ($quote->number ?? 'Quote'),
            reference_number: $quote->number,
            category: ContractCategoryEnum::ServiceAgreement,
            term_type: ContractTermTypeEnum::Indefinite,
            contractable_type: 'cleaning_object',
            contractable_id: $quote->cleaning_object_id,
            contract_template_id: null,
            body: $this->renderItemsToBody($quote),
            valid_from: now()->toDateString(),
            end_date: null,
            notes: $quote->note,
        );

        return $this->db->transaction(function () use ($quote, $contractData): Contract {
            $contract = $this->contracts->create($contractData);
            $contract->update(['quote_id' => $quote->id]);

            return $contract;
        });
    }

    /**
     * @param  array<int, QuoteItemData>  $items
     */
    private function syncItems(Quote $quote, array $items, string $tenantId, bool $isVatPayer): void
    {
        $quote->items()->delete();

        foreach ($items as $position => $itemData) {
            $rate = $isVatPayer ? $itemData->vat_rate : 0.0;
            $base = round($itemData->quantity * $itemData->unit_price * (1 - $itemData->discount_percent / 100), 2);
            $vat = round($base * $rate / 100, 2);

            QuoteItem::create([
                'tenant_id' => $tenantId,
                'quote_id' => $quote->id,
                'name' => $itemData->name,
                'description' => $itemData->description,
                'frequency' => $itemData->frequency,
                'quantity' => $itemData->quantity,
                'unit' => $itemData->unit,
                'unit_price' => $itemData->unit_price,
                'discount_percent' => $itemData->discount_percent,
                'vat_rate' => $itemData->vat_rate,
                'line_base' => $base,
                'line_vat' => $vat,
                'line_total' => round($base + $vat, 2),
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

        $subtotal = $quote->items->sum(fn (QuoteItem $item) => (float) $item->line_base);
        $vatAmount = $quote->items->sum(fn (QuoteItem $item) => (float) $item->line_vat);
        $total = round($subtotal + $vatAmount, 2);

        $groups = [];
        /** @var QuoteItem $item */
        foreach ($quote->items as $item) {
            $rate = (float) $item->vat_rate;
            $key = (string) $rate;
            if (! isset($groups[$key])) {
                $groups[$key] = ['rate' => $rate, 'base' => 0.0, 'vat' => 0.0, 'total' => 0.0];
            }

            $groups[$key]['base'] = round($groups[$key]['base'] + (float) $item->line_base, 2);
            $groups[$key]['vat'] = round($groups[$key]['vat'] + (float) $item->line_vat, 2);
            $groups[$key]['total'] = round($groups[$key]['total'] + (float) $item->line_total, 2);
        }

        $vatBreakdown = $quote->is_vat_payer ? array_values($groups) : [];
        usort($vatBreakdown, fn (array $a, array $b) => $b['rate'] <=> $a['rate']);

        return [
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $total,
            'vat_breakdown' => $vatBreakdown ?: null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributes(QuoteUpsertData $data, Tenant $tenant): array
    {
        return [
            'client_id' => $data->client_id,
            'cleaning_object_id' => $data->cleaning_object_id,
            'subject' => $data->subject,
            'issue_date' => $data->issue_date,
            'valid_until' => $data->valid_until,
            'note' => $data->note,
            'currency' => $data->currency,
            'is_vat_payer' => $tenant->is_vat_payer,
            'vat_rate' => $tenant->is_vat_payer ? $tenant->vat_rate : null,
        ];
    }

    private function renderItemsToBody(Quote $quote): string
    {
        $lines = ["<p>Cenová ponuka č. {$quote->number}</p>", '<ul>'];

        /** @var QuoteItem $item */
        foreach ($quote->items as $item) {
            $freq = $item->frequency !== null ? " ({$item->frequency})" : '';
            $line = e($item->name) . e($freq)
                . ' — ' . $item->quantity . ' ' . ($item->unit ?? 'ks')
                . ' × ' . $item->unit_price . ' ' . $quote->currency->value
                . ' = ' . $item->line_total . ' ' . $quote->currency->value;
            $lines[] = "<li>{$line}</li>";
        }

        $lines[] = '</ul>';
        $lines[] = "<p>Celkom: {$quote->total} {$quote->currency->value}</p>";

        if ($quote->note !== null) {
            $lines[] = '<p>' . e($quote->note) . '</p>';
        }

        return implode("\n", $lines);
    }
}
