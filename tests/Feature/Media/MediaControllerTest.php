<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class MediaControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $overrides */
    private function createMedia(array $overrides = []): Media
    {
        return Media::create(array_merge([
            'model_type' => User::class,
            'model_id' => (string) Str::uuid(),
            'collection_name' => 'default',
            'name' => 'test-file',
            'file_name' => 'test-file.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index — authorization
    // -------------------------------------------------------------------------

    public function test_index_is_accessible_with_view_media_permission(): void
    {
        $user = $this->userWithPermission('view media');

        $response = $this->withoutVite()->actingAs($user)->get('/media');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Index')
            ->has('media')
            ->has('filters'),
        );
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)->get('/media');

        $response->assertForbidden();
    }

    public function test_index_redirects_guest_to_login(): void
    {
        $response = $this->get('/media');

        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // Index — data
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_media(): void
    {
        $user = $this->userWithPermission('view media');
        $this->createMedia(['file_name' => 'alpha.jpg']);
        $this->createMedia(['file_name' => 'beta.png']);

        $response = $this->withoutVite()->actingAs($user)->get('/media');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Index')
            ->has('media.data'),
        );
    }

    public function test_index_filters_by_collection_name(): void
    {
        $user = $this->userWithPermission('view media');
        $this->createMedia(['collection_name' => 'avatars', 'file_name' => 'avatar.jpg']);
        $this->createMedia(['collection_name' => 'documents', 'file_name' => 'doc.pdf']);

        $response = $this->withoutVite()->actingAs($user)->get('/media?filter[collection_name]=avatars');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Index')
            ->has('media.data', 1),
        );
    }

    public function test_index_hides_media_from_another_tenant(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $this->bindTenant($foreignTenant);
        $this->createMedia(['file_name' => 'foreign.jpg']);

        $user = $this->userWithPermission('view media');

        $response = $this->withoutVite()->actingAs($user)->get('/media');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('media.data', fn ($data) => collect($data)->pluck('file_name')->doesntContain('foreign.jpg')),
        );
    }

    // -------------------------------------------------------------------------
    // Show — authorization
    // -------------------------------------------------------------------------

    public function test_show_is_accessible_with_permission(): void
    {
        $user = $this->userWithPermission('view media');
        $media = $this->createMedia();

        $response = $this->withoutVite()->actingAs($user)->get("/media/{$media->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Show')
            ->has('media'),
        );
    }

    public function test_show_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();
        $media = $this->createMedia();

        $response = $this->actingAs($user)->get("/media/{$media->id}");

        $response->assertForbidden();
    }

    public function test_show_of_media_from_another_tenant_is_forbidden(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $this->bindTenant($foreignTenant);
        $media = $this->createMedia();

        $user = $this->userWithPermission('view media');

        $response = $this->actingAs($user)->get("/media/{$media->id}");

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Show — URL resolution
    // -------------------------------------------------------------------------

    public function test_show_resolves_model_url_for_known_owner(): void
    {
        $owner = User::factory()->create();
        $user = $this->userWithPermission('view media');
        $media = $this->createMedia([
            'model_type' => User::class,
            'model_id' => (string) $owner->id,
        ]);

        $response = $this->withoutVite()->actingAs($user)->get("/media/{$media->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Show')
            ->where('media.model_url', route('users.edit', $owner)),
        );
    }

    public function test_show_returns_null_model_url_for_unmapped_owner(): void
    {
        $user = $this->userWithPermission('view media');
        $media = $this->createMedia([
            'model_type' => 'App\\Models\\UnknownModel',
            'model_id' => (string) Str::uuid(),
        ]);

        $response = $this->withoutVite()->actingAs($user)->get("/media/{$media->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Show')
            ->where('media.model_url', null),
        );
    }

    public function test_show_returns_404_for_non_numeric_id(): void
    {
        $user = $this->userWithPermission('view media');

        $response = $this->actingAs($user)->get('/media/not-a-number');

        $response->assertNotFound();
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $user = $this->userWithPermission('view media');

        $response = $this->actingAs($user)->get('/media/999999');

        $response->assertNotFound();
    }
}
