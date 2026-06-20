<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContractCategoryEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\Contract;
use App\Models\EmploymentContract;
use App\Models\TenantMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentContract>
 */
final class EmploymentContractFactory extends Factory
{
    protected $model = EmploymentContract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'employment_type' => EmploymentContractTypeEnum::Dpp,
            'position' => fake()->jobTitle(),
            'hourly_rate' => null,
            'monthly_salary' => null,
            'weekly_hours' => 40.00,
            'probation_end_date' => null,
        ];
    }

    public function forMembership(TenantMembership $membership): static
    {
        return $this->state(fn () => [
            'contract_id' => Contract::factory()->state([
                'contractable_type' => 'tenant_membership',
                'contractable_id' => $membership->id,
                'category' => ContractCategoryEnum::Employment,
                'term_type' => ContractTermTypeEnum::Fixed,
            ]),
        ]);
    }
}
