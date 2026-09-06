<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Data\Contracts\ContractUpsertData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractTermTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\PlaceholderResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlaceholderResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private function upsertData(string $objectId): ContractUpsertData
    {
        return ContractUpsertData::from([
            'title' => 'Titul zmluvy',
            'number' => null,
            'category' => ContractCategoryEnum::ServiceAgreement->value,
            'term_type' => ContractTermTypeEnum::Fixed->value,
            'contractable_type' => ContractableTypeEnum::CleaningObject->value,
            'contractable_id' => $objectId,
            'contract_template_id' => null,
            'body' => 'body',
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'notes' => null,
            'employment' => null,
        ]);
    }

    public function test_unknown_token_stays_untouched(): void
    {
        $resolved = app(PlaceholderResolverService::class)->resolve('Hello {{unknown.token}}!', ['known' => 'value']);

        $this->assertSame('Hello {{unknown.token}}!', $resolved);
    }

    public function test_null_variable_resolves_to_empty_string(): void
    {
        $resolved = app(PlaceholderResolverService::class)->resolve('IČO: {{tenant.ico}}', ['tenant.ico' => null]);

        $this->assertSame('IČO: ', $resolved);
    }

    public function test_membership_label_falls_back_to_user_name_when_profile_blank(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'first_name' => null, 'last_name' => null]);

        $variables = app(PlaceholderResolverService::class)->variablesFor($membership, $tenant, $this->upsertData($object->id));

        $user = $membership->user;
        $this->assertNotNull($user);
        $this->assertSame($user->name, $variables['employee.name']);
    }

    public function test_membership_label_prefers_profile_name_when_present(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id]);
        $membership = TenantMembership::factory()->create(['tenant_id' => $tenant->id, 'first_name' => 'Jana', 'last_name' => 'Nováková']);

        $variables = app(PlaceholderResolverService::class)->variablesFor($membership, $tenant, $this->upsertData($object->id));

        $this->assertSame('Jana Nováková', $variables['employee.name']);
    }

    public function test_quote_variables_include_item_lines_and_total(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote = Quote::factory()->forClient($client)->forObject($object)->create(['tenant_id' => $tenant->id, 'total' => '150.00']);
        QuoteItem::factory()->create([
            'tenant_id' => $tenant->id,
            'quote_id' => $quote->id,
            'description' => 'Upratovanie kancelárie',
            'quantity' => 2,
            'unit' => 'hod',
            'unit_price' => 30,
            'line_total' => 60,
        ]);

        $variables = app(PlaceholderResolverService::class)->variablesFor($object, $tenant, $this->upsertData($object->id), $quote);

        $this->assertStringContainsString('Upratovanie kancelárie', $variables['quote.items'] ?? '');
        $this->assertStringContainsString('150,00 EUR', $variables['quote.total'] ?? '');
    }

    public function test_catalog_labels_are_translated(): void
    {
        $catalog = app(PlaceholderResolverService::class)->catalog();

        $this->assertNotEmpty($catalog->cleaning_object);
        $this->assertNotEmpty($catalog->tenant_membership);
        $this->assertSame(__('app.contract_token_tenant_name'), $catalog->cleaning_object[0]->label);
    }

    public function test_resolve_is_idempotent(): void
    {
        $service = app(PlaceholderResolverService::class);
        $once = $service->resolve('{{tenant.name}}', ['tenant.name' => 'CleanCo']);
        $twice = $service->resolve($once, ['tenant.name' => 'CleanCo']);

        $this->assertSame($once, $twice);
        $this->assertSame('CleanCo', $once);
    }
}
