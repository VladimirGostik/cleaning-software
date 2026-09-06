<?php

declare(strict_types=1);

namespace Tests\Feature\Uploads;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class TemporaryUploadControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');
    }

    public function test_store_uploads_file_and_returns_uuid(): void
    {
        $user = $this->userWithPermission('upload files');
        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/uploads', ['file' => $file]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['uuid', 'name', 'file_name', 'mime_type', 'size', 'url']);
        $this->assertDatabaseCount('temporary_uploads', 1);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_store_creates_single_temporary_upload_per_session(): void
    {
        $user = $this->userWithPermission('upload files');
        $headers = ['Accept' => 'application/json'];

        $this->actingAs($user)->withHeaders($headers)->post('/uploads', ['file' => UploadedFile::fake()->image('a.jpg')]);
        $this->actingAs($user)->withHeaders($headers)->post('/uploads', ['file' => UploadedFile::fake()->image('b.jpg')]);

        $this->assertDatabaseCount('temporary_uploads', 1);
        $this->assertDatabaseCount('media', 2);
    }

    public function test_store_rejects_file_exceeding_max_size(): void
    {
        $user = $this->userWithPermission('upload files');
        $file = UploadedFile::fake()->create('large.pdf', 15_000); // 15 MB

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/uploads', ['file' => $file]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_store_is_forbidden_without_permission(): void
    {
        $user = $this->userWithPermission();
        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/uploads', ['file' => $file]);

        $response->assertForbidden();
    }

    public function test_store_redirects_guest_to_login(): void
    {
        $response = $this->post('/uploads', ['file' => UploadedFile::fake()->image('photo.jpg')]);

        $response->assertRedirect(route('login'));
    }

    public function test_destroy_deletes_own_media(): void
    {
        $user = $this->userWithPermission('upload files');
        $headers = ['Accept' => 'application/json'];

        $storeResponse = $this->actingAs($user)->withHeaders($headers)->post('/uploads', ['file' => UploadedFile::fake()->image('photo.jpg')]);
        $uuid = $storeResponse->json('uuid');

        $response = $this->actingAs($user)->delete("/uploads/{$uuid}");

        $response->assertNoContent();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_destroy_returns_404_for_other_users_media(): void
    {
        $owner = $this->userWithPermission('upload files');
        $other = $this->userWithPermission('upload files');
        $headers = ['Accept' => 'application/json'];

        $storeResponse = $this->actingAs($owner)->withHeaders($headers)->post('/uploads', ['file' => UploadedFile::fake()->image('photo.jpg')]);
        $uuid = $storeResponse->json('uuid');

        $response = $this->actingAs($other)->delete("/uploads/{$uuid}");

        $response->assertStatus(404);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_destroy_is_forbidden_without_permission(): void
    {
        $owner = $this->userWithPermission('upload files');
        $other = $this->userWithPermission();
        $headers = ['Accept' => 'application/json'];

        $storeResponse = $this->actingAs($owner)->withHeaders($headers)->post('/uploads', ['file' => UploadedFile::fake()->image('photo.jpg')]);
        $uuid = $storeResponse->json('uuid');

        $response = $this->actingAs($other)->withHeaders($headers)->delete("/uploads/{$uuid}");

        $response->assertForbidden();
    }
}
