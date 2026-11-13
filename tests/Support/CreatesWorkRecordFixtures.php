<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\JobStatusEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\ObjectVisitItem;
use App\Models\Role;
use App\Models\ScheduledJob;
use App\Models\ScheduledJobItem;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

trait CreatesWorkRecordFixtures
{
    use CreatesTaskFixtures;

    protected function objectIn(Tenant $tenant, string $name = 'Object', bool $active = true): CleaningObject
    {
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        return CleaningObject::factory()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'name' => $name,
            'street' => 'Hlavna 1',
            'city' => 'Bratislava',
            'is_active' => $active,
        ]);
    }

    /**
     * @param  array<string, string>  $prices
     * @return array<string, ObjectVisitItem>
     */
    protected function itemsFor(CleaningObject $object, array $prices = ['Kosenie' => '10.00', 'Zametanie' => '5.50']): array
    {
        $items = [];
        $position = 0;

        foreach ($prices as $name => $price) {
            $items[$name] = ObjectVisitItem::factory()->forObject($object)->create(['name' => $name, 'price' => $price, 'position' => $position++]);
        }

        return $items;
    }

    protected function assignedJob(User $user, Tenant $tenant, CleaningObject $object, JobStatusEnum $status = JobStatusEnum::Planned, string $date = '2026-11-20'): ScheduledJob
    {
        return ScheduledJob::factory()->forObject($object)->state([
            'assigned_membership_id' => $this->membershipOf($user, $tenant)->id,
            'status' => $status,
        ])->create(['tenant_id' => $tenant->id, 'scheduled_date' => $date]);
    }

    protected function membershipWithoutLogin(Tenant $tenant, string $first = 'Eva', string $last = 'Bez'): TenantMembership
    {
        return TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'is_active' => true,
            'joined_at' => now(),
            'first_name' => $first,
            'last_name' => $last,
        ]);
    }

    /**
     * @param  list<ObjectVisitItem>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function recordPayload(CleaningObject $object, array $items, array $overrides = []): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'cleaning_object_id' => $object->id,
            'item_ids' => array_map(static fn (ObjectVisitItem $item): string => $item->id, $items),
            'performed_at' => now()->subHour()->toIso8601ZuluString(),
            'note' => null,
            ...$overrides,
        ];
    }

    protected function reload(ScheduledJob $job): ScheduledJob
    {
        return ScheduledJob::withoutGlobalScopes()->whereKey($job->id)->firstOrFail();
    }

    protected function itemNamed(ScheduledJob $job, string $name): ScheduledJobItem
    {
        return ScheduledJobItem::withoutGlobalScopes()->where('cleaning_job_id', $job->id)->where('name', $name)->firstOrFail();
    }

    protected function firstItem(ScheduledJob $job): ScheduledJobItem
    {
        return ScheduledJobItem::withoutGlobalScopes()->where('cleaning_job_id', $job->id)->orderBy('name')->firstOrFail();
    }

    protected function objectOfJob(ScheduledJob $job): CleaningObject
    {
        return CleaningObject::withoutGlobalScopes()->whereKey($job->cleaning_object_id)->firstOrFail();
    }

    protected function revokeRolePermission(Tenant $tenant, string $role, string $permission): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        Role::inTenant($tenant->id)->where('name', $role)->firstOrFail()->revokePermissionTo($permission);
        $registrar->forgetCachedPermissions();
    }
}
