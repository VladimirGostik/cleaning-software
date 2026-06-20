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
    protected $model = ContractTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'category' => ContractCategoryEnum::ServiceAgreement,
            'body' => "Zmluva medzi {{tenant.name}} (IČO: {{tenant.ico}}) a klientom {{client.name}}.\n\nObjekt: {{object.name}}, {{object.address}}\n\nPodmienky upratovania...",
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
            'category' => ContractCategoryEnum::Employment,
            'body' => "Pracovná zmluva medzi {{tenant.name}} a zamestnancom {{employee.name}} ({{employee.email}}).\n\nPracovné podmienky...",
        ]);
    }
}
