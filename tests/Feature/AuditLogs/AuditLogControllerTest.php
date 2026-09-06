<?php

declare(strict_types=1);

namespace Tests\Feature\AuditLogs;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response->assertInertia(fn ($page) => $page
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
        $response->assertInertia(fn ($page) => $page
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
        $response->assertInertia(fn ($page) => $page
            ->where('activities.data', fn ($data) => collect($data)->pluck('description')->doesntContain('foreign tenant action')),
        );
    }

    public function test_show_is_accessible_with_permission(): void
    {
        $user = $this->userWithPermission('view audit logs');
        $activity = activity()->log('test action');

        $response = $this->withoutVite()->actingAs($user)->get("/audit-logs/{$activity->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
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
}
