<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Media;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsTaskAttachmentFiles;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskAttachmentWebTest extends TestCase
{
    use BuildsTaskAttachmentFiles;
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->admin = $this->member($this->alpha, 'Admin');
    }

    private function task(): Task
    {
        return Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create();
    }

    public function test_post_redirects_back_without_a_success_flash(): void
    {
        $task = $this->task();

        $response = $this->webAs($this->admin, $this->alpha)
            ->from(route('tasks.show', $task))
            ->post(route('tasks.attachments.store', $task), ['file' => $this->attachmentFile('photo.jpg')]);

        $response->assertRedirect(route('tasks.show', $task))->assertSessionMissing('success')->assertSessionHasNoErrors();
        $this->assertSame(1, Media::query()->count());
    }

    public function test_invalid_type_comes_back_as_a_validation_error_on_file(): void
    {
        $task = $this->task();

        $this->webAs($this->admin, $this->alpha)
            ->from(route('tasks.show', $task))
            ->post(route('tasks.attachments.store', $task), ['file' => $this->attachmentFile('archive.zip', 'archive.zip')])
            ->assertSessionHasErrors(['file' => __('app.task_attachment_invalid_type')]);
    }

    public function test_show_page_carries_the_attachments_prop_with_web_download_urls(): void
    {
        $task = $this->task();
        $this->webAs($this->admin, $this->alpha)->post(route('tasks.attachments.store', $task), ['file' => $this->attachmentFile('photo.jpg')]);
        $uuid = Media::query()->firstOrFail()->uuid;

        $this->webAs($this->admin, $this->alpha)->withoutVite()->get(route('tasks.index', ['task' => $task->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tasks/Index')
                ->where('selected_task.attachments_count', 1)
                ->where('selected_attachments.can_add', true)
                ->where('selected_attachments.attachments.0.id', $uuid)
                ->where('selected_attachments.attachments.0.download_url', '/tasks/'.$task->id.'/attachments/'.$uuid)
                ->where('selected_attachments.constraints.max_count', 20));
    }

    public function test_download_streams_the_file_and_delete_flashes_and_removes_it(): void
    {
        $task = $this->task();
        $this->webAs($this->admin, $this->alpha)->post(route('tasks.attachments.store', $task), ['file' => $this->attachmentFile('file.pdf')]);
        $media = Media::query()->firstOrFail();

        $this->webAs($this->admin, $this->alpha)->get(route('tasks.attachments.show', [$task, $media->uuid]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->webAs($this->admin, $this->alpha)
            ->from(route('tasks.show', $task))
            ->delete(route('tasks.attachments.destroy', [$task, $media->uuid]))
            ->assertRedirect(route('tasks.show', $task))
            ->assertSessionHas('success', __('app.task_attachment_deleted'));

        $this->assertSame(0, Media::query()->count());
        Storage::disk('local')->assertMissing($media->getPathRelativeToRoot());
    }

    public function test_guests_and_foreign_companies_are_refused(): void
    {
        $task = $this->task();
        $this->webAs($this->admin, $this->alpha)->post(route('tasks.attachments.store', $task), ['file' => $this->attachmentFile('photo.jpg')]);
        $uuid = Media::query()->firstOrFail()->uuid;
        $this->consoleContext();
        $this->app['auth']->forgetGuards();

        $this->get(route('tasks.attachments.show', [$task, $uuid]))->assertRedirect();

        $outsider = $this->member($this->beta, 'Admin');
        $this->webAs($outsider, $this->beta)->get(route('tasks.attachments.show', [$task, $uuid]))->assertNotFound();
    }

    public function test_a_user_in_two_firms_uploads_to_a_personal_task_while_another_firm_is_active(): void
    {
        $this->member($this->beta, 'Admin', $this->admin);
        $task = Task::factory()->global()->createdBy($this->admin)->create();

        $this->webAs($this->admin, $this->beta)->post(route('tasks.attachments.store', $task), ['file' => $this->attachmentFile('photo.jpg')])->assertRedirect();

        $this->assertNull(Media::query()->firstOrFail()->tenant_id);
    }
}
