<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentSourceEnum;
use App\Events\InvoiceMarkedOverdue;
use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\RecurringInvoice;
use App\Models\Tenant;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ImportLegacyInvoicesTest extends TestCase
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
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function invoice(int $id, string $number, array $extra = []): array
    {
        return [
            'id' => $id, 'firm' => 2, 'client' => 1, 'monthly_template' => 1, 'number' => $number, 'status' => 'sent',
            'name' => 'Test Street č.13,15', 'issue_date' => '2026-10-01', 'due_date' => '2026-12-01', 'delivery_date' => '2026-10-31T00:00:00',
            'billing_month' => 9, 'payment_date' => null,
            'supplier' => ['name' => 'Legacy Alpha', 'street' => 'Legacy Street 1', 'postal_code' => '81101', 'city' => 'Legacy City', 'ico' => '22 222 222', 'dic' => null, 'ic_dph' => null, 'iban' => 'sk89 7500 0000 0001 2345 6789'],
            'buyer' => ['name' => 'Invented Client', 'street' => 'Client Street 1', 'postal_code' => '81102', 'city' => 'Client City', 'ico' => '11 000 001', 'dic' => null, 'ic_dph' => null],
            'header1' => 'Owner Association', 'header2' => null, 'header3' => null, 'header4' => null,
            'above' => 'Cleaning for {mesiac/rok}', 'services' => 'Stairs',
            'items' => [['name' => 'Regular cleaning', 'quantity' => 1, 'price' => 30.0], ['name' => 'Extra', 'quantity' => 2, 'price' => 10.5]],
            ...$extra,
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $invoices
     * @return array<string, mixed>
     */
    private function payload(?array $invoices): array
    {
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
            'monthly_templates' => [[
                'id' => 1, 'firm' => 2, 'client' => 1, 'name' => 'Test Street č.13,15',
                'header1' => 'Owner Association', 'header2' => null, 'header3' => null, 'header4' => null,
                'above' => '', 'services' => '', 'items' => [['name' => 'Regular cleaning', 'quantity' => 1, 'price' => 30.0]],
                'last_billed_month' => '2026-10',
            ]],
            'last_invoice_number_2026' => ['2' => '20260041'],
            ...($invoices === null ? [] : ['invoices' => $invoices]),
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function runImport(array $payload, bool $dryRun = false): string
    {
        Storage::disk('local')->put(self::PATH, (string) json_encode($payload, JSON_THROW_ON_ERROR));
        Artisan::call('app:import-legacy', ['--owner' => 'owner@example.com', ...($dryRun ? ['--dry-run' => true] : [])]);

        return Artisan::output();
    }

    private function imported(string $number): Invoice
    {
        return Invoice::withoutGlobalScopes()->withTrashed()->where('number', $number)->with('items')->firstOrFail();
    }

    private function houseWithBilling(): CleaningObject
    {
        $this->runImport($this->payload(null));

        $object = CleaningObject::withoutGlobalScopes()->where('name', 'Test Street č.13,15')->firstOrFail();
        $object->update([
            'street' => 'House Street 13', 'city' => 'House City', 'postal_code' => '90001',
            'billing_name' => 'Owner Association Billing',
        ]);

        return $object;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function zastupene(int $id, string $number, array $extra = []): array
    {
        return $this->invoice($id, $number, ['header4' => 'Zastupene Invented Client', ...$extra]);
    }

    public function test_invoice_of_a_house_with_billing_identity_gets_the_app_buyer(): void
    {
        $this->houseWithBilling();

        $this->runImport($this->payload([$this->zastupene(1, '20260101')]));

        $invoice = $this->imported('20260101');
        $this->assertSame('Owner Association Billing', $invoice->customer_name);
        $this->assertSame('Invented Client', $invoice->customer_represented_by);
        $this->assertSame('House Street 13', $invoice->customer_street);
        $this->assertSame('House City', $invoice->customer_city);
        $this->assertSame('90001', $invoice->customer_postal_code);
    }

    public function test_existing_linked_invoice_buyer_is_resynced_once_without_side_effects(): void
    {
        $object = $this->houseWithBilling();
        $object->update(['billing_name' => null]);
        $payload = $this->payload([$this->zastupene(1, '20260101', ['status' => 'paid', 'payment_date' => '2026-10-20'])]);
        $this->runImport($payload);
        $before = $this->imported('20260101');
        $this->assertNotSame('Owner Association Billing', $before->customer_name);
        Invoice::withoutGlobalScopes()->whereKey($before->id)->update(['customer_email' => 'keep@example.com']);
        $object->update(['billing_name' => 'Owner Association Billing']);
        $activities = Activity::query()->count();
        Mail::fake();
        Notification::fake();

        $first = $this->runImport($payload);

        $invoice = $this->imported('20260101');
        $this->assertStringContainsString('1 buyers synced', $first);
        $this->assertSame('Owner Association Billing', $invoice->customer_name);
        $this->assertSame('Invented Client', $invoice->customer_represented_by);
        $this->assertSame('keep@example.com', $invoice->customer_email);
        $this->assertSame($before->number, $invoice->number);
        $this->assertSame($before->total, $invoice->total);
        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->status);
        $this->assertEquals($before->sent_at, $invoice->sent_at);
        $this->assertSame(1, InvoicePayment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->count());
        $this->assertSame($activities, Activity::query()->count());
        Mail::assertNothingSent();
        Notification::assertNothingSent();

        $updatedAt = $invoice->updated_at;
        $second = $this->runImport($payload);

        $this->assertStringContainsString('0 buyers synced', $second);
        $this->assertStringContainsString('0 created', $second);
        $this->assertEquals($updatedAt, $this->imported('20260101')->updated_at);
    }

    public function test_unlinked_invoice_keeps_the_mapper_buyer_and_is_not_counted(): void
    {
        $this->houseWithBilling();
        $payload = $this->payload([$this->zastupene(1, '20260101', ['monthly_template' => null, 'name' => 'Nowhere 1'])]);

        $this->runImport($payload);
        $second = $this->runImport($payload);

        $invoice = $this->imported('20260101');
        $this->assertNull($invoice->cleaning_object_id);
        $this->assertSame('Client Street 1', $invoice->customer_street);
        $this->assertNull($invoice->customer_represented_by);
        $this->assertStringContainsString('0 buyers synced', $second);
    }

    public function test_trashed_linked_invoice_is_not_resynced(): void
    {
        $object = $this->houseWithBilling();
        $object->update(['billing_name' => null]);
        $payload = $this->payload([$this->zastupene(1, '20260101')]);
        $this->runImport($payload);
        $before = $this->imported('20260101')->customer_name;
        $this->imported('20260101')->delete();
        $object->update(['billing_name' => 'Owner Association Billing']);

        $output = $this->runImport($payload);

        $this->assertStringContainsString('0 buyers synced', $output);
        $this->assertSame($before, $this->imported('20260101')->customer_name);
    }

    public function test_open_invoices_are_issued_or_overdue_and_nothing_is_dispatched(): void
    {
        Event::fake([InvoiceMarkedOverdue::class]);
        Notification::fake();
        Queue::fake();

        $output = $this->runImport($this->payload([
            $this->invoice(1, '20260101', ['due_date' => '2026-10-31']),
            $this->invoice(2, '20260102'),
            $this->invoice(3, '20260103', ['status' => 'created', 'issue_date' => '2026-11-01', 'due_date' => '2026-11-04']),
        ]));

        $this->assertStringContainsString('Invoices: 3 created, 0 existing, 0 skipped, 0 payments synced, 0 buyers synced.', $output);
        $this->assertSame(InvoiceStatusEnum::Overdue, $this->imported('20260101')->status);
        $this->assertSame(InvoiceStatusEnum::Issued, $this->imported('20260102')->status);
        $this->assertSame(InvoiceStatusEnum::Overdue, $this->imported('20260103')->status);

        Artisan::call('app:mark-overdue-invoices');

        Event::assertNotDispatched(InvoiceMarkedOverdue::class);
        Notification::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_invoice_is_a_snapshot_with_variable_symbol_totals_and_period(): void
    {
        $this->runImport($this->payload([$this->invoice(1, '20260143', ['billing_month' => 12, 'issue_date' => '2026-01-05'])]));

        $invoice = $this->imported('20260143');

        $this->assertSame(1, $invoice->legacy_id);
        $this->assertSame('20260143', $invoice->variable_symbol);
        $this->assertSame('2025-12-01', $invoice->period_from?->toDateString());
        $this->assertSame('2025-12-31', $invoice->period_to?->toDateString());
        $this->assertSame('2026-10-31', $invoice->delivery_date->toDateString());
        $this->assertSame('2026-01-05', $invoice->issued_at?->toDateString());
        $this->assertSame('51.00', $invoice->total);
        $this->assertSame(2, $invoice->items->count());
        $this->assertSame("Cleaning for 12/2025\nStairs", $invoice->header_text);
        $this->assertSame('Owner Association', $invoice->customer_name);
        $this->assertNull($invoice->customer_ico);
        $this->assertSame('Invented Client', $invoice->customer_represented_by);
        $this->assertSame('Legacy Alpha', $invoice->supplier_name);
        $this->assertSame('22222222', $invoice->supplier_ico);
        $this->assertSame('SK8975000000000123456789', $invoice->supplier_iban);
        $this->assertSame(InvoiceTypeEnum::Monthly, $invoice->type);
        $this->assertNull($invoice->issued_by_name);
    }

    public function test_links_resolve_client_recurring_invoice_and_object(): void
    {
        $this->runImport($this->payload([$this->invoice(1, '20260101')]));

        $invoice = $this->imported('20260101');
        $recurring = RecurringInvoice::withoutGlobalScopes()->where('name', 'Test Street č.13,15')->firstOrFail();

        $this->assertSame($recurring->id, $invoice->recurring_invoice_id);
        $this->assertSame($recurring->cleaning_object_id, $invoice->cleaning_object_id);
        $this->assertNotNull($invoice->client_id);
        $this->assertSame('Test Street č.13,15', $invoice->object_name);
    }

    public function test_unknown_client_object_and_template_import_with_warnings(): void
    {
        $output = $this->runImport($this->payload([$this->invoice(1, '20260101', ['client' => 999, 'monthly_template' => 999, 'name' => 'Nowhere 1'])]));

        $invoice = $this->imported('20260101');

        $this->assertNull($invoice->client_id);
        $this->assertNull($invoice->recurring_invoice_id);
        $this->assertNull($invoice->cleaning_object_id);
        $this->assertStringContainsString('1 x client not matched', $output);
        $this->assertStringContainsString('1 x object not matched', $output);
        $this->assertStringContainsString('1 x recurring invoice not matched', $output);
    }

    public function test_paid_invoice_gets_one_legacy_import_payment(): void
    {
        $this->runImport($this->payload([$this->invoice(1, '20260101', ['status' => 'paid', 'payment_date' => '2026-10-20'])]));

        $invoice = $this->imported('20260101');
        $payment = InvoicePayment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->sole();

        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->status);
        $this->assertSame('51.00', $invoice->paid_amount);
        $this->assertSame('2026-10-20', $invoice->paid_at?->toDateString());
        $this->assertSame(PaymentSourceEnum::LegacyImport, $payment->source);
        $this->assertSame('2026-10-20', $payment->paid_on->toDateString());
    }

    public function test_second_run_creates_nothing_and_a_deleted_invoice_is_not_recreated(): void
    {
        $payload = $this->payload([$this->invoice(1, '20260101'), $this->invoice(2, '20260102')]);
        $this->runImport($payload);
        $this->imported('20260102')->delete();

        $output = $this->runImport($payload);

        $this->assertStringContainsString('Invoices: 0 created, 2 existing, 0 skipped, 0 payments synced, 0 buyers synced.', $output);
        $this->assertSame(2, Invoice::withoutGlobalScopes()->withTrashed()->whereNotNull('legacy_id')->count());
    }

    public function test_invoice_paid_in_a_newer_payload_is_synced_once(): void
    {
        $this->runImport($this->payload([$this->invoice(1, '20260101')]));
        $paid = $this->payload([$this->invoice(1, '20260101', ['status' => 'paid', 'payment_date' => '2026-10-25'])]);

        $first = $this->runImport($paid);
        $second = $this->runImport($paid);

        $invoice = $this->imported('20260101');
        $this->assertStringContainsString('1 payments synced', $first);
        $this->assertStringContainsString('0 payments synced', $second);
        $this->assertSame(InvoiceStatusEnum::Paid, $invoice->status);
        $this->assertSame(1, InvoicePayment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->count());
    }

    public function test_number_used_by_an_app_invoice_is_skipped(): void
    {
        $this->runImport($this->payload([]));
        $tenantId = Tenant::query()->where('ico', '22222222')->value('id');
        Invoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId, 'type' => InvoiceTypeEnum::OneOff, 'number' => '20260101', 'issue_date' => '2026-10-01', 'delivery_date' => '2026-10-01', 'due_date' => '2026-10-31',
            'customer_name' => 'App buyer', 'supplier_name' => 'Legacy Alpha',
        ]);

        $output = $this->runImport($this->payload([$this->invoice(1, '20260101')]));

        $this->assertStringContainsString('Invoices: 0 created, 0 existing, 1 skipped', $output);
        $this->assertStringContainsString('1 x number taken', $output);
        $this->assertSame(0, Invoice::withoutGlobalScopes()->whereNotNull('legacy_id')->count());
    }

    public function test_invalid_rows_fail_the_run_and_write_nothing(): void
    {
        $bad = [
            $this->invoice(2, '20260102', ['items' => []]),
            $this->invoice(2, '20260102', ['status' => 'void']),
            $this->invoice(2, '20260102', ['status' => 'paid', 'payment_date' => null]),
        ];

        foreach ($bad as $row) {
            $output = $this->runImport($this->payload([$this->invoice(1, '20260101'), $row]));

            $this->assertStringContainsString('Nothing was written.', $output);
            $this->assertSame(0, Invoice::withoutGlobalScopes()->count());
        }
    }

    public function test_payload_without_invoices_and_dry_run_write_no_invoices(): void
    {
        $output = $this->runImport($this->payload(null));
        $this->assertStringContainsString('Invoices: 0 created', $output);
        $this->assertSame(0, Invoice::withoutGlobalScopes()->whereNotNull('legacy_id')->count());

        $this->runImport($this->payload([$this->invoice(1, '20260101')]), dryRun: true);
        $this->assertSame(0, Invoice::withoutGlobalScopes()->whereNotNull('legacy_id')->count());
        $this->assertSame(1, CleaningObject::withoutGlobalScopes()->count());
    }

    public function test_deleted_invoice_marked_paid_in_a_newer_payload_is_left_alone(): void
    {
        $this->runImport($this->payload([$this->invoice(1, '20260101')]));
        $this->imported('20260101')->delete();

        $output = $this->runImport($this->payload([$this->invoice(1, '20260101', ['status' => 'paid', 'payment_date' => '2026-10-25'])]));

        $this->assertStringContainsString('Invoices: 0 created, 1 existing, 0 skipped, 0 payments synced, 0 buyers synced.', $output);
        $this->assertSame(0, InvoicePayment::withoutGlobalScopes()->count());
    }

    public function test_item_unit_is_months_for_monthly_and_empty_for_one_off(): void
    {
        $this->runImport($this->payload([
            $this->invoice(1, '20260101'),
            $this->invoice(2, '20260102', ['monthly_template' => null]),
        ]));

        $this->assertSame('mes.', $this->imported('20260101')->items->firstOrFail()->unit);
        $this->assertNull($this->imported('20260102')->items->firstOrFail()->unit);
    }

    public function test_paid_invoice_with_a_zero_total_skips_the_payment_with_a_warning(): void
    {
        $output = $this->runImport($this->payload([$this->invoice(1, '20260101', [
            'status' => 'paid', 'payment_date' => '2026-10-20', 'items' => [['name' => 'Free', 'quantity' => 1, 'price' => 0]],
        ])]));

        $this->assertStringContainsString('1 x paid with a total of zero', $output);
        $this->assertSame(InvoiceStatusEnum::Issued, $this->imported('20260101')->status);
        $this->assertSame(0, InvoicePayment::withoutGlobalScopes()->count());
    }

    public function test_empty_buyer_name_fails_the_run(): void
    {
        $output = $this->runImport($this->payload([$this->invoice(1, '20260101', [
            'name' => ' ', 'header1' => null, 'buyer' => ['name' => null, 'street' => null, 'postal_code' => null, 'city' => null, 'ico' => null, 'dic' => null, 'ic_dph' => null],
        ])]));

        $this->assertStringContainsString('invoice #1: buyer name is empty', $output);
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());
    }
}
