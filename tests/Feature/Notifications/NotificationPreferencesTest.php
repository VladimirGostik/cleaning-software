<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use Database\Seeders\RoleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_renders_eight_items_with_defaults(): void
    {
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);

        $response = $this->get(route('settings.notifications'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Notifications')
            ->has('preferences.items', 8)
            ->where('preferences.items.2.type', 'invoice.overdue')
            ->where('preferences.items.2.mail', true)
            ->where('preferences.items.1.type', 'invoice.issued')
            ->where('preferences.items.1.configurable', false)
            ->where('preferences.items.1.mail', true));
    }

    public function test_update_merges_preferences_and_preserves_untouched_keys(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);

        $this->put(route('settings.notifications.update'), [
            'preferences' => [
                ['type' => 'invoice.overdue', 'mail' => false],
                ['type' => 'contract.expiring', 'mail' => true],
            ],
        ])->assertRedirect(route('settings.notifications'));

        $admin->refresh();
        $this->assertSame(['mail' => false], $admin->notification_preferences['invoice.overdue']);
        $this->assertSame(['mail' => true], $admin->notification_preferences['contract.expiring']);
    }

    public function test_update_rejects_unknown_type(): void
    {
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);

        $this->put(route('settings.notifications.update'), [
            'preferences' => [['type' => 'not-a-real-type', 'mail' => true]],
        ])->assertSessionHasErrors('preferences.0.type');
    }

    public function test_update_rejects_non_configurable_types(): void
    {
        $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);

        $this->put(route('settings.notifications.update'), [
            'preferences' => [['type' => 'invoice.issued', 'mail' => true]],
        ])->assertSessionHasErrors('preferences.0.type');

        $this->put(route('settings.notifications.update'), [
            'preferences' => [['type' => 'invitation.created', 'mail' => true]],
        ])->assertSessionHasErrors('preferences.0.type');
    }

    public function test_user_without_configure_notifications_is_forbidden(): void
    {
        $this->actingAsTenantUser('Interná upratovačka');

        $this->get(route('settings.notifications'))->assertForbidden();
        $this->put(route('settings.notifications.update'), ['preferences' => []])->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.notifications'))->assertRedirect(route('login'));
    }

    public function test_empty_preferences_is_a_no_op(): void
    {
        $admin = $this->actingAsTenantUser(RoleTemplatesSeeder::ADMIN_ROLE);
        $before = $admin->notification_preferences;

        $this->put(route('settings.notifications.update'), ['preferences' => []])
            ->assertRedirect(route('settings.notifications'));

        /** @var User $admin */
        $admin = $admin->fresh();
        $this->assertSame($before, $admin->notification_preferences);
    }
}
