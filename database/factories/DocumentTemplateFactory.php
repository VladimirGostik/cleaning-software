<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\DocumentTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTemplate>
 */
final class DocumentTemplateFactory extends Factory
{
    /** @var class-string<DocumentTemplate> */
    protected $model = DocumentTemplate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => DocumentTypeEnum::ServiceAgreement,
            'name' => 'Zmluva o vykonaní upratovacích prác',
            'body' => '<p>Táto zmluva je uzatvorená medzi {{tenant.name}} a {{client.name}} na vykonávanie upratovacích prác v priestoroch {{object.name}}, {{object.address}}.</p>',
            'show_header' => true,
            'show_footer' => true,
            'is_active' => true,
        ];
    }

    public function global(): static
    {
        return $this->state(fn () => ['tenant_id' => null]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => ['tenant_id' => $tenant->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function employment(?EmploymentContractTypeEnum $employmentType = null): static
    {
        return $this->state(fn () => [
            'name' => 'Pracovná zmluva',
            'type' => DocumentTypeEnum::Employment,
            'employment_type' => $employmentType,
            'body' => '<p>Táto pracovná zmluva je uzatvorená medzi {{tenant.name}} a {{employee.name}} ({{employee.email}}).</p>',
        ]);
    }

    public function quote(): static
    {
        return $this->state(fn () => [
            'name' => 'Cenová ponuka',
            'type' => DocumentTypeEnum::Quote,
            'body' => '<p>{{customer.name}}</p><p>{{block.quote_items}}</p><p>{{block.quote_totals}}</p>',
        ]);
    }

    public function overrideOf(DocumentTemplate $parent, Tenant $tenant): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenant->id,
            'parent_id' => $parent->id,
            'type' => $parent->type,
            'employment_type' => $parent->employment_type,
            'name' => $parent->name,
            'body' => $parent->body,
            'base_body' => $parent->body,
            'base_hash' => hash('sha256', $parent->body),
        ]);
    }
}
