<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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
        // Arrange
        $user = $this->userWithPermission('view media');

        // Act
        $response = $this->withoutVite()->actingAs($user)->get('/media');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Index')
            ->has('media')
            ->has('filters'),
        );
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get('/media');

        // Assert
        $response->assertForbidden();
    }

    public function test_index_redirects_guest_to_login(): void
    {
        // Act
        $response = $this->get('/media');

        // Assert
        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // Index — data
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_media(): void
    {
        // Arrange
        $user = $this->userWithPermission('view media');
        $this->createMedia(['file_name' => 'alpha.jpg']);
        $this->createMedia(['file_name' => 'beta.png']);

        // Act
        $response = $this->withoutVite()->actingAs($user)->get('/media');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Index')
            ->has('media.data'),
        );
    }

    public function test_index_filters_by_collection_name(): void
    {
        // Arrange
        $user = $this->userWithPermission('view media');
        $this->createMedia(['collection_name' => 'avatars', 'file_name' => 'avatar.jpg']);
        $this->createMedia(['collection_name' => 'documents', 'file_name' => 'doc.pdf']);

        // Act
        $response = $this->withoutVite()->actingAs($user)->get('/media?filter[collection_name]=avatars');

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Index')
            ->has('media.data', 1),
        );
    }

    // -------------------------------------------------------------------------
    // Show — authorization
    // -------------------------------------------------------------------------

    public function test_show_is_accessible_with_permission(): void
    {
        // Arrange
        $user = $this->userWithPermission('view media');
        $media = $this->createMedia();

        // Act
        $response = $this->withoutVite()->actingAs($user)->get("/media/{$media->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Show')
            ->has('media'),
        );
    }

    public function test_show_is_forbidden_without_permission(): void
    {
        // Arrange
        $user = User::factory()->create();
        $media = $this->createMedia();

        // Act
        $response = $this->actingAs($user)->get("/media/{$media->id}");

        // Assert
        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Show — URL resolution
    // -------------------------------------------------------------------------

    public function test_show_resolves_model_url_for_known_owner(): void
    {
        // Arrange
        $owner = User::factory()->create();
        $user = $this->userWithPermission('view media');
        $media = $this->createMedia([
            'model_type' => User::class,
            'model_id' => (string) $owner->id,
        ]);

        // Act
        $response = $this->withoutVite()->actingAs($user)->get("/media/{$media->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Show')
            ->where('media.model_url', route('users.edit', $owner)),
        );
    }

    public function test_show_returns_null_model_url_for_unmapped_owner(): void
    {
        // Arrange
        $user = $this->userWithPermission('view media');
        $media = $this->createMedia([
            'model_type' => 'App\\Models\\UnknownModel',
            'model_id' => '999',
        ]);

        // Act
        $response = $this->withoutVite()->actingAs($user)->get("/media/{$media->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Media/Show')
            ->where('media.model_url', null),
        );
    }
}
