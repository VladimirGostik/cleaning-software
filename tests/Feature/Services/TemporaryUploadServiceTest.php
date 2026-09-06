<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\TemporaryUpload;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TemporaryUploadService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TemporaryUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private TemporaryUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->service = app(TemporaryUploadService::class);
    }

    public function test_move_to_model_moves_owned_media_within_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $user = User::factory()->create();

        $upload = TemporaryUpload::create(['user_id' => $user->id, 'session_id' => 'sess-1']);
        $media = $upload->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('default');

        $target = TemporaryUpload::create(['user_id' => $user->id, 'session_id' => 'sess-2']);

        $moved = $this->service->moveToModel($target, 'default', $media->uuid, $user, 'sess-1');

        $this->assertSame($target->id, $moved->model_id);
    }

    public function test_move_to_model_throws_for_media_belonging_to_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $user = User::factory()->create();

        $upload = TemporaryUpload::create(['user_id' => $user->id, 'session_id' => 'sess-1']);
        $media = $upload->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('default');

        // Simulate a Media row whose tenant_id has diverged from its owning
        // TemporaryUpload (data drift / a prior move) — the tenant check on
        // `Media` itself must still reject it, independent of `TemporaryUpload`'s
        // own tenant scope.
        $media->update(['tenant_id' => $foreignTenant->id]);

        $target = TemporaryUpload::create(['user_id' => $user->id, 'session_id' => 'sess-2']);

        $this->expectException(ModelNotFoundException::class);
        $this->service->moveToModel($target, 'default', $media->uuid, $user, 'sess-1');
    }

    public function test_move_to_model_throws_when_not_owned_by_session_or_user(): void
    {
        $tenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $upload = TemporaryUpload::create(['user_id' => $owner->id, 'session_id' => 'sess-1']);
        $media = $upload->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('default');

        $target = TemporaryUpload::create(['user_id' => $stranger->id, 'session_id' => 'sess-2']);

        $this->expectException(ModelNotFoundException::class);
        $this->service->moveToModel($target, 'default', $media->uuid, $stranger, 'sess-2');
    }

    public function test_delete_throws_for_media_belonging_to_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $foreignTenant = Tenant::factory()->create();
        $this->bindTenant($tenant);
        $user = User::factory()->create();

        $upload = TemporaryUpload::create(['user_id' => $user->id, 'session_id' => 'sess-1']);
        $media = $upload->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('default');
        $media->update(['tenant_id' => $foreignTenant->id]);

        $this->expectException(ModelNotFoundException::class);
        $this->service->delete($media->uuid, $user, 'sess-1');
    }
}
