<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContractCategoryEnum;
use App\Models\ContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractTemplate>
 */
final class ContractTemplateFactory extends Factory
{
    /** @var class-string<ContractTemplate> */
    protected $model = ContractTemplate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Zmluva o vykonaní upratovacích prác',
            'category' => ContractCategoryEnum::ServiceAgreement,
            'body' => 'Táto zmluva je uzatvorená medzi {{tenant.name}} a {{client.name}} na vykonávanie upratovacích prác v priestoroch {{object.name}}, {{object.address}}.',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function employment(): static
    {
        return $this->state(fn () => [
            'name' => 'Pracovná zmluva',
            'category' => ContractCategoryEnum::Employment,
            'body' => 'Táto pracovná zmluva je uzatvorená medzi {{tenant.name}} a {{employee.name}} ({{employee.email}}).',
        ]);
    }
}
