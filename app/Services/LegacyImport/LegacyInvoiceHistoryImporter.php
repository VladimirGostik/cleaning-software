<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use App\Data\Invoices\InvoiceItemData;
use App\Data\Invoices\InvoicePaymentData;
use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentSourceEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentItemService;
use App\Services\InvoiceNumberService;
use App\Services\InvoiceService;
use App\Support\ActivityBatch;
use App\Support\CustomerParty;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class LegacyInvoiceHistoryImporter
{
    private const array OPEN = [InvoiceStatusEnum::Issued, InvoiceStatusEnum::Overdue];

    public function __construct(
        private InvoiceService $invoices,
        private InvoiceNumberService $numbers,
        private DocumentItemService $items,
        private LegacyRecordMapper $mapper,
    ) {}

    /**
     * @param  list<array{id: int, firm: int, client: ?int, monthly_template: ?int, number: string, status: 'created'|'sent'|'paid', name: string, issue_date: CarbonImmutable, due_date: CarbonImmutable, delivery_date: CarbonImmutable, billed_month: CarbonImmutable, payment_date: ?CarbonImmutable, header_text: ?string, buyer: CustomerParty, supplier: array<string, ?string>, items: list<array{description: string, quantity: float, unit_price: float}>}>  $rows
     * @param  array<int, string|null>  $clientIds
     * @param  array<int, string>  $recurringIds
     */
    public function import(int $firmId, Tenant $tenant, array $rows, array $clientIds, array $recurringIds, LegacyImportReport $report): void
    {
        $actor = auth()->user();
        $actor = $actor instanceof User ? $actor : null;

        ActivityBatch::run(function () use ($firmId, $tenant, $rows, $clientIds, $recurringIds, $report, $actor): void {
            foreach ($rows as $row) {
                if ($row['firm'] !== $firmId) {
                    continue;
                }

                $existing = Invoice::withTrashed()->where('legacy_id', $row['id'])->first();

                if ($existing !== null) {
                    $report->add('invoice', $row['id'], $firmId, 'existing', $row['number']);
                    $this->syncPayment($existing, $row, $firmId, $actor, $report);
                    $this->syncBuyer($existing, $row, $firmId, $report);

                    continue;
                }

                if (Invoice::query()->where('number', $row['number'])->whereNull('legacy_id')->exists()) {
                    $report->add('invoice', $row['id'], $firmId, 'skipped', $row['number'], ['number taken']);

                    continue;
                }

                $this->create($firmId, $tenant, $row, $clientIds, $recurringIds, $actor, $report);
            }
        });
    }

    /**
     * @param  array{id: int, firm: int, client: ?int, monthly_template: ?int, number: string, status: 'created'|'sent'|'paid', name: string, issue_date: CarbonImmutable, due_date: CarbonImmutable, delivery_date: CarbonImmutable, billed_month: CarbonImmutable, payment_date: ?CarbonImmutable, header_text: ?string, buyer: CustomerParty, supplier: array<string, ?string>, items: list<array{description: string, quantity: float, unit_price: float}>}  $row
     * @param  array<int, string|null>  $clientIds
     * @param  array<int, string>  $recurringIds
     */
    private function create(int $firmId, Tenant $tenant, array $row, array $clientIds, array $recurringIds, ?User $actor, LegacyImportReport $report): void
    {
        $warnings = [];
        $clientId = $row['client'] === null ? null : ($clientIds[$row['client']] ?? null);
        $recurringId = $row['monthly_template'] === null ? null : ($recurringIds[$row['monthly_template']] ?? null);

        if ($row['client'] !== null && $clientId === null) {
            $warnings[] = 'client not matched';
        }

        if ($row['monthly_template'] !== null && $recurringId === null) {
            $warnings[] = 'recurring invoice not matched';
        }

        $object = $this->object($row['name'], $recurringId);

        if ($object === null) {
            $warnings[] = 'object not matched';
        }

        $client = $clientId === null ? null : Client::query()->find($clientId);
        $buyer = $object?->hasBillingIdentity() === true ? CustomerParty::forInvoice($object, $client, $row['buyer']) : $row['buyer'];

        $issuedAt = $row['issue_date']->startOfDay();
        $overdue = $row['status'] !== 'paid' && $row['due_date']->lt(CarbonImmutable::today());

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'legacy_id' => $row['id'],
            'client_id' => $clientId,
            'cleaning_object_id' => $object?->id,
            'recurring_invoice_id' => $recurringId,
            'type' => $row['monthly_template'] === null ? InvoiceTypeEnum::OneOff : InvoiceTypeEnum::Monthly,
            'status' => $overdue ? InvoiceStatusEnum::Overdue : InvoiceStatusEnum::Issued,
            'number' => $row['number'],
            'variable_symbol' => $this->numbers->variableSymbol($row['number']),
            'period_from' => $row['billed_month']->startOfMonth(),
            'period_to' => $row['billed_month']->endOfMonth()->startOfDay(),
            'issue_date' => $row['issue_date'],
            'delivery_date' => $row['delivery_date'],
            'due_date' => $row['due_date'],
            'issued_at' => $issuedAt,
            'sent_at' => $issuedAt,
            'is_vat_payer' => false,
            'vat_rate' => null,
            'payment_type' => PaymentTypeEnum::Transfer,
            'currency' => CurrencyEnum::EUR,
            'rounding_mode' => RoundingModeEnum::None,
            'header_text' => $row['header_text'],
            ...$buyer->toInvoiceAttributes(),
            ...$this->invoices->supplierAttributes($tenant),
            ...array_filter($row['supplier'], fn (?string $value): bool => $value !== null),
            ...$this->invoices->objectAttributes($object),
        ]);

        $this->items->replacePriced($invoice->items(), array_map(
            fn (array $item): InvoiceItemData => new InvoiceItemData(
                id: null,
                description: $item['description'],
                quantity: $item['quantity'],
                unit: $row['monthly_template'] === null ? null : LegacyRecurringInvoiceImporter::LEGACY_UNIT,
                unit_price: $item['unit_price'],
            ),
            $row['items'],
        ), false);

        $invoice->load('items');
        $invoice->update($this->items->totals($invoice->items, false, RoundingModeEnum::None));

        $report->add('invoice', $row['id'], $firmId, 'created', $row['number'], $warnings);

        if ($row['status'] === 'paid') {
            $this->pay($invoice, $row, $firmId, $actor, $report);
        }
    }

    /** @param  array{id: int, number: string, status: string, payment_date: ?CarbonImmutable}  $row */
    private function syncPayment(Invoice $invoice, array $row, int $firmId, ?User $actor, LegacyImportReport $report): void
    {
        if ($invoice->trashed() || $row['status'] !== 'paid' || ! in_array($invoice->status, self::OPEN, true) || (float) $invoice->paid_amount !== 0.0) {
            return;
        }

        if ($this->pay($invoice, $row, $firmId, $actor, $report)) {
            $report->add('invoice', $row['id'], $firmId, 'payment_synced', $row['number']);
        }
    }

    /** @param  array{id: int, number: string}  $row */
    private function syncBuyer(Invoice $invoice, array $row, int $firmId, LegacyImportReport $report): void
    {
        if ($invoice->trashed()) {
            return;
        }

        $object = $invoice->cleaningObject;

        if ($object === null || ! $object->hasBillingIdentity()) {
            return;
        }

        $invoice->fill(CustomerParty::forInvoice($object, $invoice->client, CustomerParty::fromEntered($invoice))->toInvoiceAttributes());

        if (! $invoice->isDirty()) {
            return;
        }

        $invoice->save();
        $report->add('invoice', $row['id'], $firmId, 'buyer_synced', $row['number']);
    }

    /** @param  array{id: int, number: string, payment_date: ?CarbonImmutable}  $row */
    private function pay(Invoice $invoice, array $row, int $firmId, ?User $actor, LegacyImportReport $report): bool
    {
        $paidOn = $row['payment_date'] ?? throw new RuntimeException("invoice #{$row['id']}: paid without payment_date.");

        if ((float) $invoice->total <= 0) {
            $report->add('invoice', $row['id'], $firmId, 'payment_skipped', $row['number'], ['paid with a total of zero']);

            return false;
        }

        try {
            $this->invoices->recordPayment(
                $invoice,
                new InvoicePaymentData($paidOn->toDateString(), (float) $invoice->total, PaymentTypeEnum::Transfer),
                $actor,
                PaymentSourceEnum::LegacyImport,
            );

            return true;
        } catch (ValidationException $e) {
            throw new RuntimeException("invoice #{$row['id']}: ".implode(' ', $e->validator->errors()->all()), previous: $e);
        }
    }

    private function object(string $invoiceName, ?string $recurringId): ?CleaningObject
    {
        $linked = $recurringId === null ? null : RecurringInvoice::query()->find($recurringId)?->cleaning_object_id;

        if ($linked !== null) {
            return CleaningObject::query()->find($linked);
        }

        foreach (array_filter([$invoiceName, $this->mapper->standBase($invoiceName)]) as $name) {
            $object = CleaningObject::query()->whereRaw('lower(trim(name)) = lower(trim(?))', [$name])->first();

            if ($object !== null) {
                return $object;
            }
        }

        return null;
    }
}
