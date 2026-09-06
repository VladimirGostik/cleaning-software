<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\PermissionEnum;
use App\Models\Tenant;
use App\Models\TenantInterface;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class InertiaSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_and_can_and_language_shape(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantInterface::factory()->create(['tenant_id' => $tenant->id, 'color' => '#2563EB']);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        $response = $this->withoutVite()->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertInertia(function ($page) use ($tenant, $user): void {
            $page->where('tenant.active.id', $tenant->id)
                ->where('tenant.active.color', '#2563EB')
                ->has('tenant.available', 1)
                ->has('tenantColors', 8)
                ->where('auth.user.locale', $user->locale)
                ->has('languages', 3);

            $can = $page->toArray()['props']['can'];
            foreach (PermissionEnum::cases() as $case) {
                if (! array_key_exists($case->sharedKey(), $can)) {
                    throw new RuntimeException("Missing can key: {$case->sharedKey()}");
                }
            }
        });
    }

    public function test_languages_prop_includes_uk(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantInterface::factory()->create(['tenant_id' => $tenant->id]);
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        $response = $this->withoutVite()->actingAs($user)->get('/profile');

        $response->assertInertia(fn ($page) => $page->where('languages', fn ($languages) => collect($languages)->pluck('value')->contains('uk')));
    }

    public function test_tenant_shape_for_guest_is_empty(): void
    {
        $response = $this->withoutVite()->get('/login');

        $response->assertInertia(fn ($page) => $page->where('auth.user', null));
    }
}
