<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Client;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class RouteBindingTenantScopeTest extends TestCase
{
    use CreatesTaskFixtures;
    use RefreshDatabase;

    public function test_route_model_binding_runs_after_the_tenant_is_resolved(): void
    {
        $this->seedPermissionCatalog();
        $alpha = $this->firm('Alpha');
        $beta = $this->firm('Beta');
        $admin = $this->member($alpha, 'Admin');
        $foreignClient = Client::factory()->create(['tenant_id' => $beta->id]);
        $ownClient = Client::factory()->create(['tenant_id' => $alpha->id]);
        $task = Task::factory()->forTenant($alpha)->createdBy($admin)->create();

        $this->webAs($admin, $alpha);

        $this->get(route('clients.show', $foreignClient))->assertNotFound();
        $this->get(route('clients.show', $ownClient))->assertOk();
        $this->get(route('tasks.show', $task))->assertOk();
    }

    public function test_api_route_binding_runs_after_the_tenant_is_resolved(): void
    {
        $this->seedPermissionCatalog();
        $alpha = $this->firm('Alpha');
        $beta = $this->firm('Beta');
        $admin = $this->member($alpha, 'Admin');
        $this->member($beta, 'Admin', $admin);
        $task = Task::factory()->forTenant($alpha)->createdBy($admin)->create();

        $this->consoleContext();
        $this->apiAs($admin, $alpha)->getJson('/api/tasks/'.$task->id)->assertOk();
        $this->consoleContext();
        $this->apiAs($admin, $beta)->getJson('/api/tasks/'.$task->id)->assertNotFound();
    }
}
