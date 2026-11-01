<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Enums\NavIconEnum;
use App\Navigation\NavigationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class NavigationRegistryTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_admin_sees_settings_group_with_children_in_declared_order(): void
    {
        $admin = $this->actingAsTenantUser();

        $navigation = app(NavigationRegistry::class)->forUser($admin);

        $topKeys = array_map(fn ($item) => $item->key, $navigation);
        $this->assertNotContains('roles.index', $topKeys);
        $this->assertNotContains('audit-logs.index', $topKeys);
        $this->assertNotContains('media.index', $topKeys);

        $settingsGroup = collect($navigation)->firstWhere('key', 'group:settings');
        $this->assertNotNull($settingsGroup);

        $childKeys = array_map(fn ($child) => $child->key, $settingsGroup->children);
        $this->assertSame([
            'profile.show',
            'users.index',
            'settings.invoicing',
            'settings.notifications',
            'document-templates.index',
            'roles.index',
            'audit-logs.index',
            'media.index',
        ], $childKeys);
    }

    public function test_invokable_dashboard_is_the_first_top_level_item(): void
    {
        $admin = $this->actingAsTenantUser();

        $navigation = app(NavigationRegistry::class)->forUser($admin);

        $this->assertSame('dashboard', $navigation[0]->key);
        $this->assertSame('/', $navigation[0]->href);
    }

    public function test_user_without_view_permissions_does_not_see_restricted_children(): void
    {
        // 'Vedúca' template lacks ViewRoles / ViewAuditLogs / ViewMedia but keeps ViewEmployees,
        // so the settings group still renders (profile.show has no permission gate).
        $user = $this->actingAsTenantUser(roleName: 'Vedúca');

        $navigation = app(NavigationRegistry::class)->forUser($user);

        $settingsGroup = collect($navigation)->firstWhere('key', 'group:settings');
        $this->assertNotNull($settingsGroup);

        $childKeys = array_map(fn ($child) => $child->key, $settingsGroup->children);
        $this->assertNotContains('roles.index', $childKeys);
        $this->assertNotContains('audit-logs.index', $childKeys);
        $this->assertNotContains('media.index', $childKeys);
        $this->assertContains('profile.show', $childKeys);
    }

    public function test_no_top_level_item_exposes_roles_audit_logs_or_media_directly(): void
    {
        $admin = $this->actingAsTenantUser();

        $navigation = app(NavigationRegistry::class)->forUser($admin);

        $topKeys = array_map(fn ($item) => $item->key, $navigation);
        $this->assertNotContains('roles.index', $topKeys);
        $this->assertNotContains('audit-logs.index', $topKeys);
        $this->assertNotContains('media.index', $topKeys);
    }

    public function test_every_navigation_icon_is_a_nav_icon_enum_value(): void
    {
        $admin = $this->actingAsTenantUser();

        $navigation = app(NavigationRegistry::class)->forUser($admin);

        $icons = [];
        foreach ($navigation as $item) {
            $icons[] = $item->icon->value;
            foreach ($item->children as $child) {
                $icons[] = $child->icon->value;
            }
        }

        $this->assertNotEmpty($icons);
        $this->assertSame([], array_diff($icons, array_column(NavIconEnum::cases(), 'value')));
    }
}
