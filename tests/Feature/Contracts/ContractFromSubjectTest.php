<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\ContractStatusEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Documents\ContractTextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ContractFromSubjectTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(string $category, string $type, string $id, array $overrides = []): array
    {
        return array_merge([
            'category' => $category,
            'term_type' => 'indefinite',
            'contractable_type' => $type,
            'contractable_id' => $id,
            'valid_from' => now()->toDateString(),
            'title' => 'Zmluva',
            'show_header' => true,
            'show_footer' => true,
        ], $overrides);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(current_tenant_id());
    }

    public function test_generate_from_object_expands_blocks_and_resolves_values(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create(['name' => 'Acme s.r.o.']);
        $object = CleaningObject::factory()->create(['client_id' => $client->id, 'name' => 'Kancelária']);
        $template = DocumentTemplate::factory()->global()->create([
            'body' => '<p>{{block.parties}}</p><p>{{client.name}} / {{object.name}}</p><p>{{client.dic}}</p><p>{{block.signatures}}</p>',
        ]);

        $response = $this->postJson(route('contracts.generate'), $this->payload('service_agreement', 'cleaning_object', $object->id, ['document_template_id' => $template->id]));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Acme s.r.o. / Kancelária', $html);
        $this->assertStringNotContainsString('{{block', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertSame([], $response->json('missing_tokens'));
    }

    public function test_generate_from_client_has_object_tokens_left_as_missing(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create(['name' => 'Acme s.r.o.']);

        $response = $this->postJson(route('contracts.generate'), $this->payload('service_agreement', 'client', $client->id, [
            'body' => '<p>{{client.name}} {{object.name}}</p>',
        ]));

        $response->assertOk()->assertJsonPath('missing_tokens', ['object.name']);
        $this->assertStringContainsString('Acme s.r.o. {{object.name}}', $response->json('html'));
    }

    public function test_generate_without_template_or_body_uses_the_blank_skeleton(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create();

        $html = $this->postJson(route('contracts.generate'), $this->payload('service_agreement', 'client', $client->id))
            ->assertOk()->json('html');

        $this->assertSame(2, substr_count($html, '<table'));
    }

    public function test_generate_from_employee_resolves_employment_and_personal_values(): void
    {
        $user = $this->actingAsTenantUser();
        $membership = TenantMembership::factory()->withProfile()->create([
            'tenant_id' => current_tenant_id(),
            'first_name' => 'Mária',
            'last_name' => 'Nováková',
            'national_id' => '900101/1234',
        ]);

        $response = $this->postJson(route('contracts.generate'), $this->payload('employment', 'tenant_membership', $membership->id, [
            'body' => '<p>{{employee.name}} {{employee.national_id}} {{employment.type}} {{employment.monthly_salary}}</p>',
            'employment' => ['employment_type' => 'tpp', 'monthly_salary' => 1000],
        ]));

        $response->assertOk();
        $this->assertStringContainsString('Mária Nováková 900101/1234 '.EmploymentContractTypeEnum::Tpp->label(), $response->json('html'));
        $this->assertNotNull($user);
    }

    public function test_category_and_subject_must_match(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create();

        $this->postJson(route('contracts.generate'), $this->payload('employment', 'client', $client->id, [
            'employment' => ['employment_type' => 'tpp'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('contractable_type');
    }

    public function test_save_stores_a_document_and_sign_is_refused_while_tokens_remain(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create(['name' => 'Acme s.r.o.']);

        $this->post(route('contracts.store'), $this->payload('service_agreement', 'client', $client->id, [
            'body' => '<p>{{client.name}} {{object.name}}</p>',
        ]))->assertRedirect();

        $contract = Contract::query()->firstOrFail();
        $this->assertSame('<p>Acme s.r.o. {{object.name}}</p>', $contract->document?->body);
        $this->assertSame(ContractStatusEnum::Draft, $contract->status);

        $this->post(route('contracts.sign', $contract))->assertSessionHasErrors('status');
        $this->assertSame(ContractStatusEnum::Draft, $contract->fresh()->status);

        $this->put(route('contracts.update', $contract), $this->payload('service_agreement', 'client', $client->id, [
            'body' => '<p>Acme s.r.o.</p>',
        ]))->assertRedirect();

        $this->post(route('contracts.sign', $contract))->assertSessionHasNoErrors();
        $this->assertSame(ContractStatusEnum::Active, $contract->fresh()->status);
    }

    public function test_saved_body_is_sanitized(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create();

        $this->post(route('contracts.store'), $this->payload('nda', 'client', $client->id, [
            'body' => '<p>Text<script>alert(1)</script></p>',
        ]))->assertRedirect();

        $this->assertSame('<p>Text</p>', Contract::query()->firstOrFail()->document?->body);
    }

    public function test_create_page_returns_prefill_and_nulls_invalid_values(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create(['name' => 'Acme s.r.o.']);

        $this->get(route('contracts.create', ['contractable_type' => 'client', 'contractable_id' => $client->id, 'category' => 'service_agreement']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('prefill.contractable_type', 'client')
                ->where('prefill.contractable_id', $client->id)
                ->where('prefill.contractable_label', 'Acme s.r.o.')
                ->where('prefill.category', 'service_agreement'));

        $foreign = Client::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->get(route('contracts.create', ['contractable_type' => 'client', 'contractable_id' => $foreign->id, 'category' => 'employment']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('prefill.contractable_id', null)
                ->where('prefill.category', 'employment'));
    }

    public function test_migrated_legacy_blocks_are_expanded_for_display(): void
    {
        $this->actingAsTenantUser();
        $client = Client::factory()->create(['name' => 'Acme s.r.o.']);
        $contract = Contract::factory()->forObject(CleaningObject::factory()->create(['client_id' => $client->id]))
            ->withBody('<p>{{block.parties}}</p><p>Text</p><p>{{block.signatures}}</p>')
            ->create(['tenant_id' => current_tenant_id()]);

        $this->get(route('contracts.show', $contract))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('contract.missing_tokens', [])
                ->where('contract.can_be_signed', true));
        $this->assertStringContainsString('<table', (string) app(ContractTextService::class)->displayBody($contract));
        $this->assertNotNull($this->tenant());
    }
}
