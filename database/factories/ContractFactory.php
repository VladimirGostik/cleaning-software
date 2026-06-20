<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
final class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        return [
            'tenant_id' => $tenant->id,
            'contract_template_id' => null,
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $object->id,
            'category' => ContractCategoryEnum::ServiceAgreement,
            'status' => ContractStatusEnum::Draft,
            'term_type' => ContractTermTypeEnum::Fixed,
            'title' => 'Zmluva o poskytovaní upratovacích služieb',
            'reference_number' => null,
            'body' => fake()->paragraphs(3, true),
            'valid_from' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'signed_at' => null,
            'terminated_at' => null,
            'termination_reason' => null,
            'notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ContractStatusEnum::Draft]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ContractStatusEnum::Active,
            'signed_at' => now()->subDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => ContractStatusEnum::Expired,
            'end_date' => now()->subDay()->toDateString(),
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn () => [
            'status' => ContractStatusEnum::Terminated,
            'terminated_at' => now()->subDay(),
            'termination_reason' => 'Ukončenie dohodou',
        ]);
    }

    public function indefinite(): static
    {
        return $this->state(fn () => [
            'term_type' => ContractTermTypeEnum::Indefinite,
            'end_date' => null,
        ]);
    }
}
