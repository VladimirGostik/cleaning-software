<?php

declare(strict_types=1);

namespace App\Services\LegacyImport;

use App\Data\RecurringInvoices\RecurringInvoiceUpsertData;
use App\Enums\InvoiceTypeEnum;
use App\Enums\RecurringBilledMonthEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Models\CleaningObject;
use App\Models\RecurringInvoice;
use App\Models\Tenant;
use App\Services\InvoiceNumberService;
use App\Services\InvoiceSettingsService;
use App\Services\RecurringInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class LegacyRecurringInvoiceImporter
{
    private const string LEGACY_NUMBER_FORMAT = '{YYYY}{XXXX}';

    private const string LEGACY_UNIT = 'mes.';

    private const int DUE_DAYS = 30;

    private const int MAX_BACKFILL_MONTHS = 3;

    public function __construct(
        private RecurringInvoiceService $recurring,
        private InvoiceNumberService $numbers,
        private InvoiceSettingsService $settings,
        private LegacyRecordMapper $mapper,
    ) {}

    /** @param  list<array{ref: int, firm: int, client: int, object_name: string, name: string, frequency: RecurringFrequencyEnum, last_billed_month: ?CarbonImmutable, header_text: ?string, items: list<array{description: string, quantity: float, unit_price: float}>}>  $rows */
    public function import(int $firmId, Tenant $tenant, array $rows, ?string $lastNumber, LegacyImportReport $report): void
    {
        $legacyNumber = $lastNumber === null ? null : $this->mapper->legacyNumber($lastNumber);

        foreach ($rows as $row) {
            if ($row['firm'] === $firmId) {
                $this->importRow($firmId, $row, $report);
            }
        }

        if ($this->recurring->resolveTenantDefaultState($tenant->id) === RecurringDefaultStateEnum::Issued) {
            $report->add('auto_issue_firm', $firmId, $firmId, 'issued_default', $tenant->name);
        }

        if ($legacyNumber !== null) {
            $raised = $this->numbers->continueSeries($tenant, $legacyNumber['year'], $legacyNumber['last']);
            $report->add('numbering', $firmId, $firmId, $raised ? 'raised' : 'kept', $tenant->name);

            $adopted = $this->settings->adoptNumberFormat($tenant, self::LEGACY_NUMBER_FORMAT);
            $report->add('number_format', $firmId, $firmId, $adopted ? 'set' : 'kept', $tenant->name);
        }
    }

    /** @param  array{ref: int, firm: int, client: int, object_name: string, name: string, frequency: RecurringFrequencyEnum, last_billed_month: ?CarbonImmutable, header_text: ?string, items: list<array{description: string, quantity: float, unit_price: float}>}  $row */
    private function importRow(int $firmId, array $row, LegacyImportReport $report): void
    {
        $object = CleaningObject::withTrashed()
            ->whereRaw('lower(trim(name)) = lower(trim(?))', [$row['object_name']])
            ->first();

        if ($object === null || $object->trashed()) {
            $report->add('recurring', $row['ref'], $firmId, 'skipped', $row['name'], [$object === null ? 'object missing' : 'object deleted']);

            return;
        }

        $exists = RecurringInvoice::withTrashed()
            ->where('cleaning_object_id', $object->id)
            ->whereRaw('lower(trim(name)) = lower(trim(?))', [$row['name']])
            ->exists();

        if ($exists) {
            $report->add('recurring', $row['ref'], $firmId, 'existing', $row['name']);

            return;
        }

        if ($row['last_billed_month'] === null) {
            $report->add('recurring', $row['ref'], $firmId, 'skipped', $row['name'], ['no last_billed_month']);

            return;
        }

        if ($row['items'] === []) {
            $report->add('recurring', $row['ref'], $firmId, 'skipped', $row['name'], ['no items']);

            return;
        }

        $months = $this->dueMonths($row['last_billed_month'], $row['frequency']);

        if (count($months['billed']) > self::MAX_BACKFILL_MONTHS) {
            $report->add('recurring', $row['ref'], $firmId, 'skipped', $row['name'], ['backfill over cap']);

            return;
        }

        $ri = $this->recurring->create($this->data($row, $object, $months['start']));
        $report->add('recurring', $row['ref'], $firmId, 'created', $row['name']);

        foreach ($months['billed'] as $billedMonth) {
            if ($this->recurring->generateInvoiceFromTemplate($ri, $billedMonth) !== null) {
                $report->add('draft', $row['ref'], $firmId, 'created', $row['name'].' '.RecurringBilledMonthEnum::formatMonth($billedMonth));
            }
        }
    }

    /** @return array{billed: list<CarbonImmutable>, start: CarbonImmutable} */
    private function dueMonths(CarbonImmutable $last, RecurringFrequencyEnum $frequency): array
    {
        $today = CarbonImmutable::today();
        $billed = [];
        $next = $last->addMonthsNoOverflow($frequency->monthsInterval());

        while (RecurringBilledMonthEnum::Previous->runDate($next, 1)->lte($today)) {
            $billed[] = $next;
            $next = $next->addMonthsNoOverflow($frequency->monthsInterval());
        }

        return ['billed' => $billed, 'start' => RecurringBilledMonthEnum::Previous->runDate($next, 1)];
    }

    /** @param  array{ref: int, firm: int, client: int, object_name: string, name: string, frequency: RecurringFrequencyEnum, last_billed_month: ?CarbonImmutable, header_text: ?string, items: list<array{description: string, quantity: float, unit_price: float}>}  $row */
    private function data(array $row, CleaningObject $object, CarbonImmutable $start): RecurringInvoiceUpsertData
    {
        try {
            return RecurringInvoiceUpsertData::validateAndCreate([
                'client_id' => $object->client_id,
                'cleaning_object_id' => $object->id,
                'name' => $row['name'],
                'type' => InvoiceTypeEnum::Monthly->value,
                'frequency' => $row['frequency']->value,
                'billed_month' => RecurringBilledMonthEnum::Previous->value,
                'day_of_month' => 1,
                'auto_issue' => false,
                'auto_send' => false,
                'start_date' => $start->toDateString(),
                'end_date' => null,
                'occurrences_limit' => null,
                'due_days' => self::DUE_DAYS,
                'customer_name' => null,
                'customer_representative' => null,
                'customer_ico' => null,
                'customer_dic' => null,
                'customer_vat_number' => null,
                'customer_street' => null,
                'customer_city' => null,
                'customer_postal_code' => null,
                'customer_country' => null,
                'customer_email' => null,
                'note' => null,
                'items' => array_map(fn (array $item): array => [
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => self::LEGACY_UNIT,
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => 0,
                    'vat_rate' => 0,
                ], $row['items']),
                'constant_symbol' => null,
                'header_text' => $row['header_text'],
                'footer_text' => null,
            ]);
        } catch (ValidationException $e) {
            throw new RuntimeException("recurring #{$row['ref']}: ".implode(' ', $e->validator->errors()->all()), previous: $e);
        }
    }
}
