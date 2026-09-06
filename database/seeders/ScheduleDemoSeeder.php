<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\CurrencyEnum;
use App\Enums\JobStatusEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Enums\SupportedLanguage;
use App\Enums\TaskFrequencyEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Role;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\ContractService;
use App\Services\JobService;
use App\Services\WorkBreakdownService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Demo data for the schedule module: one signed service-agreement contract generated from an
 * accepted quote (breakdown + 30 days of jobs), and one "Interná upratovačka" employee assigned
 * to a few of the generated jobs so a developer can log in and see own-only scoping in action.
 */
final class ScheduleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('ico', '12345678')->first();

        if ($tenant === null) {
            return;
        }

        if (Quote::where('number', 'DEMO-ROZVRH')->exists()) {
            return;
        }

        app()->instance('current_tenant_id', $tenant->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        $client = Client::query()->orderBy('created_at')->first();
        $object = $client !== null
            ? CleaningObject::query()->where('client_id', $client->id)->where('is_active', true)->orderBy('created_at')->first()
            : null;

        if ($client === null || $object === null) {
            return;
        }

        $quote = Quote::create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'cleaning_object_id' => $object->id,
            'status' => QuoteStatusEnum::Accepted,
            'kind' => QuoteKindEnum::Itemized,
            'number' => 'DEMO-ROZVRH',
            'subject' => 'Pravidelné upratovanie — demo',
            'issue_date' => now()->subDays(10)->toDateString(),
            'valid_until' => now()->addDays(20)->toDateString(),
            'sent_at' => now()->subDays(9),
            'accepted_at' => now()->subDays(8),
            'is_vat_payer' => true,
            'vat_rate' => '23.00',
            'currency' => CurrencyEnum::EUR,
            'subtotal' => '100.00',
            'vat_amount' => '23.00',
            'total' => '123.00',
            'vat_breakdown' => [['rate' => 23.0, 'base' => 100.0, 'vat' => 23.0, 'total' => 123.0]],
        ]);

        QuoteItem::create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Pravidelné upratovanie kancelárií',
            'frequency' => TaskFrequencyEnum::Weekly1x,
            'quantity' => 1,
            'unit' => 'ks',
            'unit_price' => '100.00',
            'discount_percent' => 0,
            'vat_rate' => '23.00',
            'line_base' => '100.00',
            'line_vat' => '23.00',
            'line_total' => '123.00',
            'position' => 0,
        ]);

        $contract = Contract::create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'contractable_type' => ContractableTypeEnum::CleaningObject->value,
            'contractable_id' => $object->id,
            'category' => ContractCategoryEnum::ServiceAgreement,
            'status' => ContractStatusEnum::Draft,
            'term_type' => ContractTermTypeEnum::Indefinite,
            'title' => 'Zmluva o pravidelnom upratovaní — demo',
            'number' => null,
            'body' => 'Zmluvné podmienky pravidelného upratovania.',
            'valid_from' => now()->subDays(7)->toDateString(),
        ]);

        // Signs the contract — the `ContractSigned` listener generates the work breakdown
        // synchronously; the follow-up `GenerateScheduledJobsJob` dispatch is queued (afterCommit)
        // and would not run without a worker, so the jobs themselves are generated directly below.
        app(ContractService::class)->sign($contract);

        $freshContract = Contract::findOrFail($contract->id);
        $breakdown = app(WorkBreakdownService::class)->generateFromContract($freshContract);

        if ($breakdown !== null) {
            $horizonDays = config('scheduling.horizon_days', 30);
            $horizonDays = is_numeric($horizonDays) ? (int) $horizonDays : 30;
            $period = today()->toPeriod(today()->addDays($horizonDays - 1));
            app(JobService::class)->generateForBreakdown($breakdown->load('tasks'), $period);
        }

        $cleaner = User::firstOrCreate(
            ['email' => 'cleaner@example.com'],
            [
                'name' => 'Demo Upratovačka',
                'password' => Hash::make('password'),
                'locale' => SupportedLanguage::getDefault()->value,
                'is_active' => true,
            ],
        );
        $cleaner->forceFill(['email_verified_at' => $cleaner->email_verified_at ?? now()])->save();

        $membership = TenantMembership::firstOrCreate(
            ['user_id' => $cleaner->id, 'tenant_id' => $tenant->id],
            ['is_active' => true, 'joined_at' => now(), 'first_name' => 'Demo', 'last_name' => 'Upratovačka'],
        );

        $role = Role::inTenant($tenant->id)->where('name', 'Interná upratovačka')->first();

        if ($role !== null) {
            $cleaner->syncRoles([$role]);
        }

        ScheduledJob::query()
            ->where('cleaning_object_id', $object->id)
            ->orderBy('scheduled_date')
            ->limit(3)
            ->get()
            ->each(function (ScheduledJob $job) use ($membership): void {
                $job->update(['assigned_membership_id' => $membership->id, 'status' => JobStatusEnum::Planned]);
            });
    }
}
