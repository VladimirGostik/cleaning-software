<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Notifications\InvitationCreated;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class InvitationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    // -------------------------------------------------------------------------
    // Happy paths
    // -------------------------------------------------------------------------

    public function test_invitation_created_notification_sent_at_registration(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms_accepted' => true,
            'company' => [
                'name' => 'Test Company s.r.o.',
                'ico' => '12345678',
                'dic' => null,
                'vat_number' => null,
                'is_vat_payer' => false,
                'address_line' => 'Test Street 1',
                'city' => 'Bratislava',
                'postal_code' => '81001',
                'country' => 'SK',
            ],
            'invites' => [
                ['email' => 'invitee1@example.com', 'role_name' => 'Vedúca'],
                ['email' => 'invitee2@example.com', 'role_name' => 'Sekretárka'],
            ],
        ]);

        $response->assertRedirect(route('dashboard'));

        Notification::assertSentOnDemand(
            InvitationCreated::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'invitee1@example.com',
        );

        Notification::assertSentOnDemand(
            InvitationCreated::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'invitee2@example.com',
        );
    }

    public function test_owner_email_excluded_from_invitations(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms_accepted' => true,
            'company' => [
                'name' => 'Test Company',
                'ico' => '11111111',
                'dic' => null,
                'vat_number' => null,
                'is_vat_payer' => false,
                'address_line' => 'Street 1',
                'city' => 'Bratislava',
                'postal_code' => '81001',
                'country' => 'SK',
            ],
            'invites' => [
                ['email' => 'owner@example.com', 'role_name' => 'Vedúca'], // same as owner — excluded
            ],
        ]);

        // Owner email must not receive invitation
        Notification::assertSentOnDemandTimes(InvitationCreated::class, 0);
    }

    // -------------------------------------------------------------------------
    // Edge case: notification carries correct token
    // -------------------------------------------------------------------------

    public function test_invitation_notification_carries_non_empty_token(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Owner',
            'email' => 'owner2@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms_accepted' => true,
            'company' => [
                'name' => 'Token Company',
                'ico' => '22222222',
                'dic' => null,
                'vat_number' => null,
                'is_vat_payer' => false,
                'address_line' => 'Street 2',
                'city' => 'Bratislava',
                'postal_code' => '81001',
                'country' => 'SK',
            ],
            'invites' => [
                ['email' => 'tokentest@example.com', 'role_name' => 'Vedúca'],
            ],
        ]);

        Notification::assertSentOnDemand(
            InvitationCreated::class,
            fn (InvitationCreated $notification) => strlen($notification->token) === 64,
        );
    }
}
