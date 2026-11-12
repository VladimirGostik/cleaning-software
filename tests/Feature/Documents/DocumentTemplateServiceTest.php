<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Data\DocumentTemplates\DocumentTemplatePreviewData;
use App\Data\DocumentTemplates\DocumentTemplateUpsertData;
use App\Enums\DocumentTemplateScopeEnum;
use App\Enums\DocumentTemplateStateEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Services\Documents\DocumentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DocumentTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentTemplateService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->bindTenant($this->tenant);
        $this->service = app(DocumentTemplateService::class);
    }

    private function upsert(string $body, DocumentTypeEnum $type = DocumentTypeEnum::ServiceAgreement, ?EmploymentContractTypeEnum $employmentType = null): DocumentTemplateUpsertData
    {
        return new DocumentTemplateUpsertData('Šablóna', $type, $body, true, true, true, $employmentType);
    }

    public function test_customize_creates_an_override_in_base_state(): void
    {
        $global = DocumentTemplate::factory()->global()->create(['body' => '<p>A</p>']);

        $override = $this->service->customize($global, $this->tenant);

        $this->assertSame(DocumentTemplateScopeEnum::Override, $override->templateScope());
        $this->assertSame(DocumentTemplateStateEnum::Base, $override->state());
        $this->assertSame($global->id, $override->parent_id);
    }

    public function test_edited_override_is_customized_and_survives_a_global_edit_as_base_changed(): void
    {
        $global = DocumentTemplate::factory()->global()->create(['body' => '<p>A</p>']);
        $override = $this->service->customize($global, $this->tenant);
        $this->service->update($override, $this->upsert('<p>Moja</p>'));

        $this->assertSame(DocumentTemplateStateEnum::Customized, $override->fresh()->state());

        $global->update(['body' => '<p>B</p>']);

        $fresh = DocumentTemplate::query()->findOrFail($override->id);
        $this->assertSame(DocumentTemplateStateEnum::BaseChanged, $fresh->state());
        $this->assertSame('<p>Moja</p>', $fresh->body);
        $this->assertSame('<p>A</p>', $fresh->base_body);
    }

    public function test_acknowledge_keeps_the_text_and_clears_base_changed(): void
    {
        $global = DocumentTemplate::factory()->global()->create(['body' => '<p>A</p>']);
        $override = $this->service->customize($global, $this->tenant);
        $this->service->update($override, $this->upsert('<p>Moja</p>'));
        $global->update(['body' => '<p>B</p>']);

        $this->service->acknowledge($override->fresh());

        $fresh = $override->fresh();
        $this->assertSame('<p>Moja</p>', $fresh->body);
        $this->assertSame(DocumentTemplateStateEnum::Customized, $fresh->state());
    }

    public function test_reset_removes_the_override_and_the_global_text_applies_again(): void
    {
        $global = DocumentTemplate::factory()->global()->create(['body' => '<p>A</p>']);
        $override = $this->service->customize($global, $this->tenant);

        $this->service->reset($override);

        $this->assertSoftDeleted('document_templates', ['id' => $override->id]);
        $ids = array_map(fn (DocumentTemplate $t) => $t->id, $this->service->options(DocumentTypeEnum::ServiceAgreement));
        $this->assertSame([$global->id], $ids);
    }

    public function test_picker_shows_the_override_instead_of_its_global(): void
    {
        $global = DocumentTemplate::factory()->global()->create();
        $other = DocumentTemplate::factory()->global()->create(['name' => 'Iná']);
        $override = $this->service->customize($global, $this->tenant);

        $ids = array_map(fn (DocumentTemplate $t) => $t->id, $this->service->options(DocumentTypeEnum::ServiceAgreement));

        $this->assertEqualsCanonicalizing([$override->id, $other->id], $ids);
    }

    public function test_other_firms_overrides_and_templates_are_invisible(): void
    {
        $global = DocumentTemplate::factory()->global()->create();
        $foreignTenant = Tenant::factory()->create();
        DocumentTemplate::factory()->overrideOf($global, $foreignTenant)->create();
        DocumentTemplate::factory()->forTenant($foreignTenant)->create(['name' => 'Cudzia']);

        $names = array_map(fn (DocumentTemplate $t) => $t->name, $this->service->options(DocumentTypeEnum::ServiceAgreement));

        $this->assertSame([$global->name], $names);
    }

    public function test_employment_options_never_offer_a_different_employment_type(): void
    {
        $tpp = DocumentTemplate::factory()->global()->employment(EmploymentContractTypeEnum::Tpp)->create(['name' => 'TPP']);
        DocumentTemplate::factory()->global()->employment(EmploymentContractTypeEnum::SelfEmployed)->create(['name' => 'Živnosť']);
        $any = DocumentTemplate::factory()->global()->employment()->create(['name' => 'Všetky']);

        $ids = array_map(fn (DocumentTemplate $t) => $t->id, $this->service->options(DocumentTypeEnum::Employment, EmploymentContractTypeEnum::Tpp));

        $this->assertEqualsCanonicalizing([$tpp->id, $any->id], $ids);
    }

    public function test_unknown_tokens_for_the_type_are_rejected_on_body(): void
    {
        try {
            $this->service->create($this->upsert('<p>{{employee.name}}</p>'), $this->tenant);
            $this->fail('expected validation exception');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('body', $e->errors());
        }
    }

    public function test_body_is_sanitized_on_save(): void
    {
        $template = $this->service->create($this->upsert('<p>A<script>alert(1)</script></p>'), $this->tenant);

        $this->assertSame('<p>A</p>', $template->body);
    }

    public function test_preview_uses_sample_data_and_reports_unknown_tokens(): void
    {
        $result = $this->service->preview(
            new DocumentTemplatePreviewData('<p>{{tenant.name}} {{employee.name}}</p>', DocumentTypeEnum::ServiceAgreement, true, false),
            $this->tenant,
        );

        $this->assertStringContainsString('Ukážková s.r.o.', $result->html);
        $this->assertSame(['employee.name'], $result->unknown_tokens);
        $this->assertNotNull($result->letterhead);
        $this->assertSame([], $result->footer);
    }

    public function test_override_body_is_validated_against_its_own_type_not_the_submitted_one(): void
    {
        $global = DocumentTemplate::factory()->global()->create(['type' => DocumentTypeEnum::ServiceAgreement, 'body' => '<p>A</p>']);
        $override = $this->service->customize($global, $this->tenant);

        try {
            $this->service->update($override, $this->upsert('<p>{{employee.national_id}}</p>', DocumentTypeEnum::Employment));
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('body', $exception->errors());
        }

        $this->assertSame(DocumentTypeEnum::ServiceAgreement, $override->refresh()->type);
    }

    public function test_global_template_body_is_validated_against_the_submitted_type(): void
    {
        $global = DocumentTemplate::factory()->global()->create(['type' => DocumentTypeEnum::ServiceAgreement, 'body' => '<p>A</p>']);

        $this->service->update($global, $this->upsert('<p>{{employee.national_id}}</p>', DocumentTypeEnum::Employment));

        $this->assertSame(DocumentTypeEnum::Employment, $global->refresh()->type);
    }

    public function test_deleting_a_global_template_keeps_firm_overrides_usable(): void
    {
        $global = DocumentTemplate::factory()->global()->create(['body' => '<p>A</p>']);
        $override = $this->service->customize($global, $this->tenant);
        $this->service->update($override, $this->upsert('<p>Moja</p>'));

        $this->service->delete($global);

        $this->assertSoftDeleted('document_templates', ['id' => $global->id]);
        $this->assertNotSoftDeleted('document_templates', ['id' => $override->id]);

        $fresh = $override->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame($global->id, $fresh->parent?->id);
        $this->assertSame(DocumentTemplateStateEnum::Customized, $fresh->state());
        $ids = array_map(fn (DocumentTemplate $t) => $t->id, $this->service->options(DocumentTypeEnum::ServiceAgreement));
        $this->assertSame([$override->id], $ids);

        $this->service->acknowledge($fresh);
        $this->service->reset($fresh);
        $this->assertSoftDeleted('document_templates', ['id' => $override->id]);
    }

    public function test_customize_twice_returns_the_existing_override(): void
    {
        $global = DocumentTemplate::factory()->global()->create(['body' => '<p>A</p>']);

        $first = $this->service->customize($global, $this->tenant);
        $second = $this->service->customize($global, $this->tenant);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DocumentTemplate::query()->where('parent_id', $global->id)->count());
    }

    public function test_default_for_prefers_firm_rows_over_global_and_skips_overridden_globals(): void
    {
        $default = fn (): ?string => $this->service->defaultFor(DocumentTypeEnum::Quote, $this->tenant->id)?->id;

        $this->assertNull($default());

        $global = DocumentTemplate::factory()->global()->quote()->create(['name' => 'A global']);
        $this->assertSame($global->id, $default());

        $own = DocumentTemplate::factory()->quote()->forTenant($this->tenant)->create(['name' => 'Z own']);
        $this->assertSame($own->id, $default());

        $own->update(['is_active' => false]);
        $override = $this->service->customize($global, $this->tenant);
        $this->assertSame($override->id, $default());
    }

    public function test_default_for_ignores_other_firms_and_works_without_a_bound_tenant(): void
    {
        $other = Tenant::factory()->create();
        DocumentTemplate::factory()->quote()->forTenant($other)->create();
        app()->forgetInstance('current_tenant_id');

        $this->assertNull($this->service->defaultFor(DocumentTypeEnum::Quote, $this->tenant->id));
    }

    public function test_quote_preview_uses_the_quote_footer_and_sample_blocks_while_other_types_keep_the_letterhead_footer(): void
    {
        $data = new DocumentTemplatePreviewData('<p>{{block.quote_parties}}</p><p>{{block.quote_items}}</p>', DocumentTypeEnum::Quote, true, true, null);

        $quote = $this->service->preview($data, $this->tenant);

        $this->assertCount(2, $quote->footer);
        $this->assertNotNull($quote->letterhead);
        $this->assertStringContainsString('Jednorazové umývanie okien', $quote->html);
        $this->assertStringContainsString('V zastúpení:', $quote->html);
        $this->assertStringContainsString('Správcovská s.r.o.', $quote->html);

        $agreement = $this->service->preview(new DocumentTemplatePreviewData('<p>{{block.quote_items}}</p>', DocumentTypeEnum::ServiceAgreement, true, true, null), $this->tenant);
        $this->assertStringNotContainsString('letter-spacing', $agreement->html);
        $this->assertStringContainsString('border-bottom: 1px solid #333333', $agreement->html);
    }
}
