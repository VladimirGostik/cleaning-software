<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\NotificationTypeEnum;
use App\Models\Tenant;
use App\Notifications\InvoiceOverdue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Happy paths
    // -------------------------------------------------------------------------

    public function test_update_preferences_persists_to_user(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');

        $this->put(route('settings.notifications.update'), [
            'preferences' => [
                NotificationTypeEnum::InvoiceOverdue->value => false,
                NotificationTypeEnum::ContractExpiring->value => true,
            ],
        ])->assertRedirect();

        $user->refresh();
        $prefs = $user->notification_preferences;
        $this->assertFalse($prefs[NotificationTypeEnum::InvoiceOverdue->value]['mail']);
        $this->assertTrue($prefs[NotificationTypeEnum::ContractExpiring->value]['mail']);
    }

    public function test_via_skips_mail_when_user_pref_disabled(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $user->update([
            'notification_preferences' => [
                NotificationTypeEnum::InvoiceOverdue->value => ['mail' => false],
            ],
        ]);

        $notification = new InvoiceOverdue($tenant->id, 'fake-invoice-id');

        $channels = $notification->via($user);

        $this->assertNotContains('mail', $channels);
    }

    public function test_via_includes_mail_when_user_pref_enabled(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        $user->update([
            'notification_preferences' => [
                NotificationTypeEnum::InvoiceOverdue->value => ['mail' => true],
            ],
        ]);

        $notification = new InvoiceOverdue($tenant->id, 'fake-invoice-id');

        $channels = $notification->via($user);

        $this->assertContains('mail', $channels);
    }

    public function test_default_mail_enabled_applied_when_key_absent(): void
    {
        $user = $this->actingAsTenantUser('Vlastník');
        /** @var Tenant $tenant */
        $tenant = Tenant::where('owner_id', $user->id)->first();

        // No prefs stored — default for InvoiceOverdue is true
        $user->update(['notification_preferences' => null]);

        $notification = new InvoiceOverdue($tenant->id, 'fake-invoice-id');
        $channels = $notification->via($user);

        // InvoiceOverdue::defaultMailEnabled() = true
        $this->assertContains('mail', $channels);
    }

    public function test_settings_page_renders(): void
    {
        $this->actingAsTenantUser('Vlastník');

        $this->get(route('settings.notifications'))->assertOk();
    }

    // -------------------------------------------------------------------------
    // Failure paths
    // -------------------------------------------------------------------------

    public function test_update_preferences_rejects_unknown_type_key(): void
    {
        $this->actingAsTenantUser('Vlastník');

        $response = $this->put(route('settings.notifications.update'), [
            'preferences' => [
                'not.a.real.type' => true,
            ],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_update_preferences_rejects_non_configurable_type(): void
    {
        $this->actingAsTenantUser('Vlastník');

        // InvoiceIssued is not userConfigurable
        $response = $this->put(route('settings.notifications.update'), [
            'preferences' => [
                NotificationTypeEnum::InvoiceIssued->value => true,
            ],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_unauthenticated_cannot_update_settings(): void
    {
        $this->post(route('logout'));

        $this->put(route('settings.notifications.update'), ['preferences' => []])
            ->assertRedirect(route('login'));
    }
}
