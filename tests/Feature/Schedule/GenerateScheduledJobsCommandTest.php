<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Jobs\GenerateScheduledJobsJob;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\WorkBreakdown;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class GenerateScheduledJobsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_job_per_active_breakdown_with_active_contract(): void
    {
        Bus::fake();

        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->active()->create(['tenant_id' => $tenant->id]);
        $breakdown = WorkBreakdown::factory()->forContract($contract)->create(['tenant_id' => $tenant->id]);

        $this->artisan('app:generate-scheduled-jobs')->assertExitCode(0);

        Bus::assertDispatched(GenerateScheduledJobsJob::class, fn (GenerateScheduledJobsJob $job) => $job->workBreakdownId === $breakdown->id);
    }

    public function test_skips_inactive_breakdown(): void
    {
        Bus::fake();

        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->active()->create(['tenant_id' => $tenant->id]);
        WorkBreakdown::factory()->inactive()->forContract($contract)->create(['tenant_id' => $tenant->id]);

        $this->artisan('app:generate-scheduled-jobs');

        Bus::assertNotDispatched(GenerateScheduledJobsJob::class);
    }

    public function test_skips_breakdown_with_draft_contract(): void
    {
        Bus::fake();

        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $contract = Contract::factory()->forObject($object)->draft()->create(['tenant_id' => $tenant->id]);
        WorkBreakdown::factory()->forContract($contract)->create(['tenant_id' => $tenant->id]);

        $this->artisan('app:generate-scheduled-jobs');

        Bus::assertNotDispatched(GenerateScheduledJobsJob::class);
    }

    public function test_bypasses_tenant_scope_across_tenants(): void
    {
        Bus::fake();

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->bindTenant($tenantA);
        $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
        $objectA = CleaningObject::factory()->create(['tenant_id' => $tenantA->id, 'client_id' => $clientA->id]);
        $contractA = Contract::factory()->forObject($objectA)->active()->create(['tenant_id' => $tenantA->id]);
        $breakdownA = WorkBreakdown::factory()->forContract($contractA)->create(['tenant_id' => $tenantA->id]);

        $this->bindTenant($tenantB);
        $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
        $objectB = CleaningObject::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);
        $contractB = Contract::factory()->forObject($objectB)->active()->create(['tenant_id' => $tenantB->id]);
        $breakdownB = WorkBreakdown::factory()->forContract($contractB)->create(['tenant_id' => $tenantB->id]);

        $this->artisan('app:generate-scheduled-jobs');

        Bus::assertDispatched(GenerateScheduledJobsJob::class, fn (GenerateScheduledJobsJob $job) => $job->workBreakdownId === $breakdownA->id);
        Bus::assertDispatched(GenerateScheduledJobsJob::class, fn (GenerateScheduledJobsJob $job) => $job->workBreakdownId === $breakdownB->id);
    }

    public function test_command_is_scheduled_daily(): void
    {
        $schedule = app(Schedule::class);

        $matching = collect($schedule->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'app:generate-scheduled-jobs'));

        $this->assertNotEmpty($matching);
        $this->assertSame('0 0 * * *', $matching->sole()->expression);
    }
}
