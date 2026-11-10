<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Ai\AnalyzesExpenses;
use App\Enums\PermissionEnum;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fakes\FakeExpenseAnalyzer;

trait CreatesExpenseFixtures
{
    use CreatesTaskFixtures;

    protected FakeExpenseAnalyzer $analyzer;

    protected Tenant $alpha;

    protected Tenant $beta;

    protected User $accountant;

    protected function setUpExpenses(): void
    {
        $this->seedPermissionCatalog();
        $this->analyzer = new FakeExpenseAnalyzer;
        $this->app->instance(AnalyzesExpenses::class, $this->analyzer);

        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->accountant = $this->member($this->alpha, 'Účtovníčka');
        $this->member($this->beta, 'Účtovníčka', $this->accountant);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function expensePayload(array $overrides = []): array
    {
        return [
            'date' => '2026-10-01',
            'amount' => '45.50',
            'currency' => 'EUR',
            'vat_amount' => '7.58',
            'supplier_name' => 'Shell Slovakia',
            'description' => 'Fuel',
            'category' => 'fuel',
            ...$overrides,
        ];
    }

    /** @param  list<PermissionEnum>  $permissions */
    protected function grantPermissions(User $user, Tenant $tenant, array $permissions): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->givePermissionTo(array_map(static fn (PermissionEnum $permission): string => $permission->value, $permissions));
    }
}
