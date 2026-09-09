<?php

declare(strict_types=1);

namespace Tests\Feature\AuditLogs;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class AuditLogControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_index_is_accessible_with_view_audit_logs_permission(): void
    {
        $user = $this->userWithPermission('view audit logs');

        $response = $this->withoutVite()->actingAs($user)->get('/audit-logs');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('AuditLogs/Index')
            ->has('activities')
            ->has('filters'),
        );
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)->get('/audit-logs');

        $response->assertForbidden();
    }

    public function test_index_redirects_guest_to_login(): void
    {
        $response = $this->get('/audit-logs');

        $response->assertRedirect(route('login'));
    }

    public function test_index_returns_paginated_activities(): void
    {
        $user = $this->userWithPermission('view audit logs');
        activity()->log('first action');
        activity()->log('second action');

        $response = $this->withoutVite()->actingAs($user)->get('/audit-logs');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('AuditLogs/Index')
            ->has('activities.data'),
        );
    }

    public function test_index_hides_activities_from_another_tenant(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $this->bindTenant($foreignTenant);
        activity()->log('foreign tenant action');

        $user = $this->userWithPermission('view audit logs');

        $response = $this->withoutVite()->actingAs($user)->get('/audit-logs');

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activities.data', fn ($data) => collect($data)->pluck('description')->doesntContain('foreign tenant action')),
        );
    }

    public function test_show_is_accessible_with_permission(): void
    {
        $user = $this->userWithPermission('view audit logs');
        $activity = activity()->log('test action');

        $response = $this->withoutVite()->actingAs($user)->get("/audit-logs/{$activity->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('AuditLogs/Show')
            ->has('activity'),
        );
    }

    public function test_show_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();
        $activity = activity()->log('test action');

        $response = $this->actingAs($user)->get("/audit-logs/{$activity->id}");

        $response->assertForbidden();
    }

    public function test_show_of_activity_from_another_tenant_is_forbidden(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $this->bindTenant($foreignTenant);
        $activity = activity()->log('foreign action');

        $user = $this->userWithPermission('view audit logs');

        $response = $this->actingAs($user)->get("/audit-logs/{$activity->id}");

        $response->assertForbidden();
    }

    public function test_show_exposes_attribute_changes_and_causer(): void
    {
        $user = $this->userWithPermission('view audit logs');
        $this->actingAs($user);

        $client = Client::factory()->create([
            'tenant_id' => current_tenant_id(),
            'name' => 'Pôvodný názov',
        ]);
        $client->update(['name' => 'Nový názov']);

        $activity = Activity::query()
            ->where('subject_type', $client->getMorphClass())
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $response = $this->withoutVite()->get("/audit-logs/{$activity->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('AuditLogs/Show')
            ->where('activity.attribute_changes.attributes.name', 'Nový názov')
            ->where('activity.attribute_changes.old.name', 'Pôvodný názov')
            ->where('activity.causer_name', $user->name)
            ->where('activity.causer_email', $user->email),
        );
    }
}
