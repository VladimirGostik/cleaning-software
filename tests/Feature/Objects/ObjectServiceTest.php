<?php

declare(strict_types=1);

namespace Tests\Feature\Objects;

use App\Data\Objects\ObjectUpsertData;
use App\Enums\ObjectTypeEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Tenant;
use App\Services\ObjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ObjectServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivate_sets_is_active_false_and_logs_activity(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        app(ObjectService::class)->deactivate($object);

        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => false]);
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $object->id,
            'subject_type' => 'cleaning_object',
        ]);
    }

    public function test_reactivate_sets_is_active_true(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $object = CleaningObject::factory()->inactive()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        app(ObjectService::class)->reactivate($object);

        $this->assertDatabaseHas('objects', ['id' => $object->id, 'is_active' => true]);
    }

    public function test_paginate_with_view_all_objects_returns_all_tenant_objects(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Admin', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $paginator = app(ObjectService::class)->paginate(Request::create('/objects'), $actor);

        $this->assertSame(3, $paginator->total());
    }

    public function test_paginate_without_view_all_objects_returns_empty(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = $this->actingAsTenantUser('Interná upratovačka', $tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        CleaningObject::factory()->count(3)->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $paginator = app(ObjectService::class)->paginate(Request::create('/objects'), $actor);

        $this->assertSame(0, $paginator->total());
    }

    public function test_create_fills_tenant_id_from_bound_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $data = new ObjectUpsertData(
            client_id: $client->id,
            type: ObjectTypeEnum::Office,
            name: 'Service Test Office',
            street: null,
            city: null,
            postal_code: null,
            country: 'SK',
            access_code: null,
            key_box_code: null,
            key_count: null,
            special_instructions: null,
            area_sqm: null,
            floor: null,
            is_active: true,
        );

        $object = app(ObjectService::class)->create($data);

        $this->assertSame($tenant->id, $object->tenant_id);
    }
}
