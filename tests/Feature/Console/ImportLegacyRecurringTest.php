<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\RecurringBilledMonthEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Models\CleaningObject;
use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use App\Models\RecurringInvoice;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ImportLegacyRecurringTest extends TestCase
{
    use RefreshDatabase;

    private const string PATH = 'import/legacy-cleaning-system.json';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-05 10:00'));
        app(RegistrationService::class)->createOwner('Owner', 'owner@example.com', 'password123', 'Placeholder Firm 99999999', '99999999', null);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $template = fn (int $id, string $name, array $extra = []): array => [
            'id' => $id, 'firm' => 2, 'client' => 1, 'name' => $name,
            'header1' => 'Owner Association', 'header2' => null, 'header3' => '811 03 Elsewhere', 'header4' => null,
            'above' => "Cleaning for {mesiac/rok}\n\n", 'services' => 'Stairs and halls',
            'items' => [['name' => 'Regular cleaning', 'quantity' => 1, 'price' => 30.0]],
            'last_billed_month' => '2026-09', ...$extra,
        ];
        $manual = fn (int $id, string $name, string $frequency, string $last, array $extra = []): array => [
            'id' => $id, 'firm' => 2, 'client' => 1, 'name' => $name, 'object_type' => 'residential_building',
            'header1' => 'Manual Association', 'header2' => null, 'header3' => null, 'header4' => null,
            'above' => 'Manual {mesiac/rok}', 'services' => '', 'frequency' => $frequency, 'last_billed_month' => $last,
            'items' => [['name' => 'Manual cleaning', 'quantity' => 1, 'price' => 45.5]], ...$extra,
        ];

        return [
            'firms' => [[
                'id' => 2, 'company_name' => 'Legacy Alpha', 'company_address' => 'Legacy Street 1', 'postal_code' => '81101',
                'city' => 'Legacy City', 'company_ico' => '22222222', 'company_dic' => null, 'company_iban' => null, 'register' => null,
            ]],
            'clients' => [[
                'id' => 1, 'company_name' => 'Invented Client', 'company_address' => 'Client Street 1', 'postal_code' => '81102',
                'city' => 'Client City', 'company_ico' => '11000001', 'company_dic' => null, 'company_iban' => null, 'register' => null,
            ]],
            'firm_clients_2026' => [],
            'monthly_templates' => [
                $template(1, 'Test Street č.13,15'),
                $template(2, 'Test Street č.13,15 kont.stojisko', ['header1' => null, 'above' => '', 'services' => '']),
                $template(3, 'Solo Office', ['last_billed_month' => null]),
            ],
            'manual_recurring' => [
                $manual(11, 'Manual Two Old', 'every_2_months', '2026-08'),
                $manual(12, 'Manual Two Fresh', 'every_2_months', '2026-09'),
                $manual(13, 'Manual Monthly', 'monthly', '2026-09', ['object_type' => 'office']),
                $manual(14, 'Recurring On Shared Object', 'monthly', '2026-09', ['object_name' => 'Test Street č.13,15']),
            ],
            'last_invoice_number_2026' => ['2' => '20260041'],
            ...$overrides,
        ];
    }

    /** @param  array<string, mixed>|null  $payload */
    private function runImport(?array $payload = null, bool $dryRun = false): string
    {
        Storage::disk('local')->put(self::PATH, (string) json_encode($payload ?? $this->payload(), JSON_THROW_ON_ERROR));
        Artisan::call('app:import-legacy', ['--owner' => 'owner@example.com', ...($dryRun ? ['--dry-run' => true] : [])]);

        return Artisan::output();
    }

    private function recurring(string $name): RecurringInvoice
    {
        return RecurringInvoice::withoutGlobalScopes()->where('name', $name)->firstOrFail();
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->where('ico', '22222222')->firstOrFail();
    }

    public function test_templates_and_manual_groups_become_recurring_invoices_with_october_drafts(): void
    {
        $output = $this->runImport();

        $this->assertStringContainsString('Created: 1 firms, 1 clients, 5 objects, 6 recurring invoices, 5 drafts.', $output);

        $house = $this->recurring('Test Street č.13,15');
        $this->assertSame(RecurringBilledMonthEnum::Previous, $house->billed_month);
        $this->assertSame(RecurringFrequencyEnum::Monthly, $house->frequency);
        $this->assertSame(1, $house->day_of_month);
        $this->assertSame(30, $house->due_days);
        $this->assertFalse($house->auto_issue);
        $this->assertFalse($house->auto_send);
        $this->assertSame('2026-12-01', $house->next_run_at?->toDateString());
        $this->assertSame("Cleaning for {mesiac/rok}\nStairs and halls", $house->header_text);
        $this->assertSame(CleaningObject::withoutGlobalScopes()->where('name', 'Test Street č.13,15')->value('id'), $house->cleaning_object_id);

        $this->assertSame('mes.', $house->items()->firstOrFail()->unit);

        $draft = Invoice::withoutGlobalScopes()->where('recurring_invoice_id', $house->id)->with('items')->firstOrFail();
        $this->assertSame('mes.', $draft->items->firstOrFail()->unit);
        $this->assertSame('2026-10-01', $draft->period_from?->toDateString());
        $this->assertSame('2026-10-31', $draft->delivery_date->toDateString());
        $this->assertSame("Cleaning for 10/2026\nStairs and halls", $draft->header_text);
        $this->assertSame('2026-12-05', $draft->due_date->toDateString());
        $this->assertSame('draft', $draft->status->value);
        $this->assertSame('Regular cleaning', $draft->items->firstOrFail()->description);
    }

    public function test_merged_stand_gets_its_own_recurring_invoice_on_the_house_object(): void
    {
        $this->runImport();

        $stand = $this->recurring('Test Street č.13,15 kont.stojisko');

        $this->assertSame($this->recurring('Test Street č.13,15')->cleaning_object_id, $stand->cleaning_object_id);
        $this->assertSame(1, CleaningObject::withoutGlobalScopes()->where('name', 'like', 'Test Street%')->count());
    }

    public function test_manual_rows_backfill_by_their_last_billed_month(): void
    {
        $this->runImport();

        $old = $this->recurring('Manual Two Old');
        $this->assertSame(RecurringFrequencyEnum::EveryTwoMonths, $old->frequency);
        $this->assertSame('2027-01-01', $old->next_run_at?->toDateString());
        $this->assertSame(['2026-10-01'], Invoice::withoutGlobalScopes()->where('recurring_invoice_id', $old->id)->get()->map(fn (Invoice $invoice): ?string => $invoice->period_from?->toDateString())->all());

        $fresh = $this->recurring('Manual Two Fresh');
        $this->assertSame('2026-12-01', $fresh->next_run_at?->toDateString());
        $this->assertSame(0, Invoice::withoutGlobalScopes()->where('recurring_invoice_id', $fresh->id)->count());

        $monthly = $this->recurring('Manual Monthly');
        $this->assertSame('2026-12-01', $monthly->next_run_at?->toDateString());
        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('recurring_invoice_id', $monthly->id)->count());
        $this->assertSame('office', CleaningObject::withoutGlobalScopes()->findOrFail($monthly->cleaning_object_id)->type->value);
    }

    public function test_manual_row_with_object_name_uses_that_object_and_keeps_its_own_name(): void
    {
        $this->runImport();

        $shared = $this->recurring('Recurring On Shared Object');

        $this->assertSame($this->recurring('Test Street č.13,15')->cleaning_object_id, $shared->cleaning_object_id);
        $this->assertSame(0, CleaningObject::withoutGlobalScopes()->where('name', 'Recurring On Shared Object')->count());
    }

    public function test_row_without_last_billed_month_is_skipped_with_a_warning_but_its_object_is_imported(): void
    {
        $output = $this->runImport();

        $this->assertStringContainsString('1 x no last_billed_month', $output);
        $this->assertSame(0, RecurringInvoice::withoutGlobalScopes()->where('name', 'Solo Office')->count());
        $this->assertSame(1, CleaningObject::withoutGlobalScopes()->where('name', 'Solo Office')->count());
    }

    public function test_second_run_creates_no_recurring_invoices_and_no_drafts_and_a_deleted_one_is_not_recreated(): void
    {
        $this->runImport();
        $this->recurring('Manual Monthly')->delete();
        $recurring = RecurringInvoice::withoutGlobalScopes()->withTrashed()->count();
        $invoices = Invoice::withoutGlobalScopes()->withTrashed()->count();

        $output = $this->runImport();

        $this->assertStringContainsString('0 recurring invoices, 0 drafts.', $output);
        $this->assertSame($recurring, RecurringInvoice::withoutGlobalScopes()->withTrashed()->count());
        $this->assertSame($invoices, Invoice::withoutGlobalScopes()->withTrashed()->count());
    }

    public function test_deleted_object_skips_its_recurring_invoice(): void
    {
        $objectsOnly = $this->payload(['monthly_templates' => [
            ['id' => 1, 'firm' => 2, 'client' => 1, 'name' => 'Test Street č.13,15', 'header1' => 'Owner Association', 'above' => '', 'services' => '', 'items' => [], 'last_billed_month' => null],
            ['id' => 2, 'firm' => 2, 'client' => 1, 'name' => 'Test Street č.13,15 kont.stojisko', 'above' => '', 'services' => '', 'items' => [], 'last_billed_month' => null],
        ]]);
        $this->runImport($objectsOnly);
        CleaningObject::withoutGlobalScopes()->where('name', 'Test Street č.13,15')->firstOrFail()->delete();

        $output = $this->runImport();

        $this->assertStringContainsString('3 x object deleted', $output);
        $this->assertSame(0, RecurringInvoice::withoutGlobalScopes()->where('name', 'like', 'Test Street%')->count());
    }

    public function test_dry_run_persists_nothing(): void
    {
        $output = $this->runImport(dryRun: true);

        $this->assertStringContainsString('6 recurring invoices, 5 drafts.', $output);
        $this->assertSame(0, RecurringInvoice::withoutGlobalScopes()->count());
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());
        $this->assertSame(0, InvoiceNumberSequence::withoutGlobalScopes()->count());
        $this->assertSame(0, Tenant::query()->where('ico', '22222222')->count());
    }

    public function test_numbering_continues_the_legacy_series_and_adopts_the_default_format(): void
    {
        $this->runImport();

        $tenant = $this->tenant();
        $this->assertSame(41, InvoiceNumberSequence::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('year', 2026)->value('last_number'));
        $this->assertSame('{YYYY}{XXXX}', $tenant->invoice_number_format);
    }

    public function test_higher_sequence_and_custom_format_are_kept(): void
    {
        $this->runImport();
        $tenant = $this->tenant();
        InvoiceNumberSequence::withoutGlobalScopes()->where('tenant_id', $tenant->id)->update(['last_number' => 60]);
        $tenant->update(['invoice_number_format' => 'INV{XXXX}']);

        $this->runImport();

        $this->assertSame(60, InvoiceNumberSequence::withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('last_number'));
        $this->assertSame('INV{XXXX}', $tenant->refresh()->invoice_number_format);
    }

    public function test_invalid_legacy_number_fails_the_run_and_writes_nothing(): void
    {
        $output = $this->runImport($this->payload(['last_invoice_number_2026' => ['2' => '26-41']]));

        $this->assertStringContainsString('Nothing was written.', $output);
        $this->assertSame(0, Tenant::query()->where('ico', '22222222')->count());
        $this->assertSame(0, RecurringInvoice::withoutGlobalScopes()->count());
    }

    public function test_postal_code_is_corrected_only_when_it_equals_the_clients_code_or_blank(): void
    {
        $this->runImport();
        $house = CleaningObject::withoutGlobalScopes()->where('name', 'Test Street č.13,15')->firstOrFail();
        $solo = CleaningObject::withoutGlobalScopes()->where('name', 'Solo Office')->firstOrFail();
        $manual = CleaningObject::withoutGlobalScopes()->where('name', 'Manual Monthly')->firstOrFail();
        $house->update(['postal_code' => '811 02']);
        $solo->update(['postal_code' => '999 99']);
        $manual->update(['postal_code' => null]);

        $output = $this->runImport($this->payload(['object_postal_codes' => [
            'Test Street č.13,15' => '821 06',
            'Solo Office' => '821 06',
            'Manual Monthly' => '821 06',
        ]]));

        $this->assertStringContainsString('Postal codes filled: 1, corrected: 1, skipped invalid: 0.', $output);
        $this->assertSame('821 06', $house->refresh()->postal_code);
        $this->assertSame('999 99', $solo->refresh()->postal_code);
        $this->assertSame('821 06', $manual->refresh()->postal_code);

        $again = $this->runImport($this->payload(['object_postal_codes' => ['Test Street č.13,15' => '821 06']]));
        $this->assertStringContainsString('Postal codes filled: 0, corrected: 0, skipped invalid: 0.', $again);
    }

    public function test_object_without_a_map_entry_keeps_its_postal_code(): void
    {
        $this->runImport();
        $house = CleaningObject::withoutGlobalScopes()->where('name', 'Test Street č.13,15')->firstOrFail();
        $house->update(['postal_code' => '811 02']);

        $this->runImport($this->payload(['object_postal_codes' => ['Other Object' => '821 06']]));

        $this->assertSame('811 02', $house->refresh()->postal_code);
    }

    public function test_firms_with_an_auto_issue_default_are_counted_in_the_output(): void
    {
        $this->assertStringNotContainsString('recurring_default_state = issued', $this->runImport());

        TenantInterface::query()->where('tenant_id', $this->tenant()->id)->update(['recurring_default_state' => 'issued']);

        $this->assertStringContainsString('1 imported firm(s) have recurring_default_state = issued', $this->runImport());
    }

    public function test_backfill_over_three_months_is_skipped_with_a_warning(): void
    {
        $payload = $this->payload();
        $payload['manual_recurring'] = [[
            'id' => 21, 'firm' => 2, 'client' => 1, 'name' => 'Old Group', 'object_type' => 'office',
            'header1' => 'Old Association', 'above' => '', 'services' => '', 'frequency' => 'monthly', 'last_billed_month' => '2026-01',
            'items' => [['name' => 'Cleaning', 'quantity' => 1, 'price' => 10.0]],
        ], [
            'id' => 22, 'firm' => 2, 'client' => 1, 'name' => 'Edge Group', 'object_type' => 'office',
            'header1' => 'Edge Association', 'above' => '', 'services' => '', 'frequency' => 'monthly', 'last_billed_month' => '2026-07',
            'items' => [['name' => 'Cleaning', 'quantity' => 1, 'price' => 10.0]],
        ]];

        $output = $this->runImport($payload);

        $this->assertStringContainsString('1 x backfill over cap', $output);
        $this->assertSame(0, RecurringInvoice::withoutGlobalScopes()->where('name', 'Old Group')->count());
        $this->assertSame(1, CleaningObject::withoutGlobalScopes()->where('name', 'Old Group')->count());
        $this->assertSame(3, Invoice::withoutGlobalScopes()->whereIn('recurring_invoice_id', RecurringInvoice::withoutGlobalScopes()->where('name', 'Edge Group')->select('id'))->count());
    }

    public function test_unknown_manual_frequency_fails_the_run_and_writes_nothing(): void
    {
        $output = $this->runImport($this->payload(['manual_recurring' => [[
            'id' => 11, 'firm' => 2, 'client' => 1, 'name' => 'Weekly Group', 'object_type' => 'office',
            'header1' => 'Weekly Association', 'above' => '', 'services' => '', 'frequency' => 'weekly', 'last_billed_month' => '2026-09',
            'items' => [['name' => 'Cleaning', 'quantity' => 1, 'price' => 10.0]],
        ]]]));

        $this->assertStringContainsString('recurring #11: invalid frequency.', $output);
        $this->assertSame(0, Tenant::query()->where('ico', '22222222')->count());
        $this->assertSame(0, RecurringInvoice::withoutGlobalScopes()->count());
    }
}
