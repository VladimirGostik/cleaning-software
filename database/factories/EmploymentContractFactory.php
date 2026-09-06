<?php

declare(strict_types=1);

namespace Database\Factories;

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
    /** @var class-string<EmploymentContract> */
    protected $model = EmploymentContract::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory()->forMembership(TenantMembership::factory()),
            'employment_type' => EmploymentContractTypeEnum::Dpp,
            'position' => null,
            'hourly_rate' => null,
            'monthly_salary' => null,
            'weekly_hours' => 40,
            'probation_end_date' => null,
        ];
    }

    public function forContract(Contract $contract): static
    {
        return $this->state(fn () => ['contract_id' => $contract->id]);
    }
}
