<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CurrencyEnum;
use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
final class ExpenseFactory extends Factory
{
    /** @var class-string<Expense> */
    protected $model = Expense::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'created_by_user_id' => User::factory(),
            'status' => ExpenseStatusEnum::Confirmed,
            'date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'amount' => fake()->randomFloat(2, 1, 500),
            'currency' => CurrencyEnum::EUR,
            'vat_amount' => null,
            'supplier_name' => fake()->company(),
            'description' => null,
            'category' => ExpenseCategoryEnum::Other,
            'source' => ExpenseSourceEnum::Manual,
            'bank_reference' => null,
            'external_hash' => null,
            'ai_proposal' => null,
            'confirmed_at' => now(),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => ['tenant_id' => $tenant->id]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => ['created_by_user_id' => $user->id]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ExpenseStatusEnum::Draft, 'category' => null, 'confirmed_at' => null]);
    }
}
