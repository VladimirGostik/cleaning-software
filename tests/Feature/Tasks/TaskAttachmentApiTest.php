<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Activity;
use App\Models\Media;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TaskAttachmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsTaskAttachmentFiles;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TaskAttachmentApiTest extends TestCase
{
    use BuildsTaskAttachmentFiles;
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $admin;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->admin = $this->member($this->alpha, 'Admin');
        $this->editor = $this->member($this->alpha, 'Vedúca');
    }

    private function url(Task $task, mixed $attachment = null): string
    {
        return '/api/tasks/'.$task->id.'/attachments'.(is_string($attachment) ? '/'.$attachment : '');
    }

    /** @return array<string, mixed> */
    private function properties(Activity $activity): array
    {
        return $activity->propertiesArray() ?? [];
    }

    private function firmTask(?User $creator = null): Task
    {
        return Task::factory()->forTenant($this->alpha)->createdBy($creator ?? $this->admin)->create();
    }

    /** @return TestResponse<JsonResponse> */
    private function upload(User $user, Task $task, string $fixture, ?string $name = null, ?Tenant $as = null): TestResponse
    {
        return $this->apiAs($user, $as ?? $this->alpha)->post($this->url($task), ['file' => $this->attachmentFile($fixture, $name)], ['Accept' => 'application/json']);
    }

    /** @return array<string, array{string, string, string}> */
    public static function acceptedTypes(): array
    {
        return [
            'jpeg' => ['photo.jpg', 'image/jpeg', 'jpg'],
            'png' => ['photo.png', 'image/png', 'png'],
            'webp' => ['photo.webp', 'image/webp', 'webp'],
            'heic' => ['photo.heic', 'image/heic', 'heic'],
            'heif' => ['photo.heif', 'image/heif', 'heif'],
            'pdf' => ['file.pdf', 'application/pdf', 'pdf'],
            'doc' => ['file.doc', 'application/msword', 'doc'],
            'xls' => ['file.xls', 'application/vnd.ms-excel', 'xls'],
            'docx' => ['file.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'],
            'xlsx' => ['file.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
        ];
    }

    #[DataProvider('acceptedTypes')]
    public function test_every_type_of_the_table_is_accepted(string $fixture, string $mime, string $extension): void
    {
        $task = $this->firmTask();

        $response = $this->upload($this->admin, $task, $fixture)->assertCreated();

        $response->assertJsonPath('mime_type', $mime)->assertJsonPath('name', $fixture);
        $media = Media::query()->where('uuid', $response->json('id'))->firstOrFail();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.'.$extension.'$/', $media->file_name);
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_zip_container_named_docx_or_xlsx_gets_the_canonical_office_type(): void
    {
        $task = $this->firmTask();

        $this->upload($this->admin, $task, 'archive.zip', 'report.DOCX')
            ->assertCreated()
            ->assertJsonPath('mime_type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->upload($this->admin, $task, 'archive.zip', 'sheet.xlsx')
            ->assertCreated()
            ->assertJsonPath('mime_type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertSame(['docx', 'xlsx'], Media::query()->orderBy('id')->get()->map(fn (Media $m): string => pathinfo($m->file_name, PATHINFO_EXTENSION))->all());
    }

    public function test_ole_header_is_stored_as_the_office_type_of_its_extension(): void
    {
        $task = $this->firmTask();

        $this->upload($this->admin, $task, 'file.doc', 'old.XLS')
            ->assertCreated()
            ->assertJsonPath('mime_type', 'application/vnd.ms-excel');
    }

    /** @return array<string, array{string, string}> */
    public static function rejectedFiles(): array
    {
        return [
            'zip named zip' => ['archive.zip', 'archive.zip'],
            'zip named pdf' => ['archive.zip', 'invoice.pdf'],
            'svg' => ['image.svg', 'image.svg'],
            'svg renamed jpg' => ['image.svg', 'image.jpg'],
            'html' => ['page.html', 'page.html'],
            'video' => ['clip.mp4', 'clip.mp4'],
            'ole named pdf' => ['file.doc', 'file.pdf'],
        ];
    }

    #[DataProvider('rejectedFiles')]
    public function test_other_types_are_rejected_on_the_file_field(string $fixture, string $name): void
    {
        $task = $this->firmTask();

        $this->upload($this->admin, $task, $fixture, $name)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file')
            ->assertJsonPath('errors.file.0', __('app.task_attachment_invalid_type'));

        $this->assertSame(0, Media::query()->count());
    }

    public function test_heic_is_not_previewable_and_downloads_as_an_attachment(): void
    {
        $task = $this->firmTask();
        $id = $this->upload($this->admin, $task, 'photo.heic')->assertCreated()->assertJsonPath('previewable', false)->json('id');

        $response = $this->apiAs($this->admin, $this->alpha)->get($this->url($task, $id))->assertOk();

        $this->assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_images_and_pdf_are_inline_and_every_download_is_private_and_nosniff(): void
    {
        $task = $this->firmTask();

        foreach (['photo.jpg' => true, 'file.pdf' => false] as $fixture => $previewable) {
            $id = $this->upload($this->admin, $task, $fixture)->assertCreated()->assertJsonPath('previewable', $previewable)->json('id');
            $response = $this->apiAs($this->admin, $this->alpha)->get($this->url($task, $id))->assertOk();

            $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        }
    }

    public function test_download_returns_the_stored_bytes_with_an_ascii_fallback_name(): void
    {
        $task = $this->firmTask();
        $id = $this->upload($this->admin, $task, 'file.pdf', 'faktúra 2026.pdf')->assertCreated()->json('id');

        $response = $this->apiAs($this->admin, $this->alpha)->get($this->url($task, $id))->assertOk();

        $this->assertSame(file_get_contents(base_path('tests/Fixtures/task-attachments/file.pdf')), $response->streamedContent());
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('filename=', $disposition);
        $this->assertStringContainsString("filename*=utf-8''", $disposition);
    }

    public function test_an_editor_adds_files_to_open_done_and_nice_to_have_tasks(): void
    {
        foreach (['open' => [], 'done' => ['status' => 'done'], 'nice_to_have' => ['status' => 'nice_to_have']] as $attributes) {
            $task = Task::factory()->forTenant($this->alpha)->createdBy($this->editor)->create($attributes);

            $this->upload($this->editor, $task, 'photo.jpg')->assertCreated();
        }

        $this->assertSame(3, Media::query()->count());
    }

    public function test_adding_requires_edit_tasks(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');
        $role = Role::inTenant($this->alpha->id)->where('name', 'Sekretárka')->firstOrFail();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->alpha->id);
        $role->revokePermissionTo('edit tasks');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $task = $this->firmTask($secretary);

        $this->upload($secretary, $task, 'photo.jpg')->assertForbidden();

        $this->apiAs($secretary, $this->alpha)->getJson($this->url($task))->assertOk()->assertJsonPath('can_add', false);
    }

    public function test_a_user_who_cannot_see_the_task_can_neither_add_nor_read(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');
        $hidden = $this->firmTask($this->admin);

        $this->upload($secretary, $hidden, 'photo.jpg')->assertForbidden();
        $this->apiAs($secretary, $this->alpha)->getJson($this->url($hidden))->assertForbidden();
    }

    public function test_list_is_oldest_first_with_constraints_and_rights(): void
    {
        $task = $this->firmTask();
        $first = $this->upload($this->admin, $task, 'photo.jpg')->json('id');
        $second = $this->upload($this->admin, $task, 'file.pdf')->json('id');

        $response = $this->apiAs($this->admin, $this->alpha)->getJson($this->url($task))->assertOk();

        $this->assertSame([$first, $second], $response->json('attachments.*.id'));
        $response->assertJsonPath('can_add', true)
            ->assertJsonPath('constraints.max_size_kb', 10240)
            ->assertJsonPath('constraints.max_count', 20)
            ->assertJsonPath('attachments.0.uploaded_by_name', $this->admin->name)
            ->assertJsonPath('attachments.0.can_delete', true)
            ->assertJsonPath('attachments.0.download_url', '/api/tasks/'.$task->id.'/attachments/'.(is_string($first) ? $first : ''));
        $this->assertContains('image/heic', (array) $response->json('constraints.allowed_mimes'));
        $this->assertContains('jpeg', (array) $response->json('constraints.allowed_extensions'));
    }

    public function test_post_returns_the_same_name_as_the_list(): void
    {
        $task = $this->firmTask();

        /** @var array<string, mixed> $posted */
        $posted = $this->upload($this->admin, $task, 'file.pdf', "C:\\fakepath\\a\u{200B}b  .pdf")->assertCreated()->json();
        $listed = $this->apiAs($this->admin, $this->alpha)->getJson($this->url($task))->json('attachments.0');

        $this->assertSame('ab  .pdf', $posted['name']);
        $this->assertSame($posted, $listed);
        $this->assertSame(TaskAttachmentService::cleanName("C:\\fakepath\\a\u{200B}b  .pdf"), $posted['name']);
        $this->assertStringNotContainsString('fakepath', Media::query()->firstOrFail()->file_name);
    }

    public function test_size_is_exactly_what_the_client_sent(): void
    {
        $task = $this->firmTask();

        $this->upload($this->admin, $task, 'photo.png')->assertCreated()->assertJsonPath('size', (int) filesize(base_path('tests/Fixtures/task-attachments/photo.png')));
    }

    public function test_file_over_ten_megabytes_is_refused(): void
    {
        $task = $this->firmTask();

        $this->apiAs($this->admin, $this->alpha)
            ->post($this->url($task), ['file' => $this->attachmentBytes('big.pdf', 10241)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', __('app.task_attachment_too_large', ['max' => 10]));

        $this->apiAs($this->admin, $this->alpha)
            ->post($this->url($task), ['file' => $this->attachmentBytes('edge.pdf', 10240)], ['Accept' => 'application/json'])
            ->assertCreated();
    }

    public function test_the_twenty_first_file_is_refused_and_the_twentieth_succeeds(): void
    {
        $task = $this->firmTask();

        for ($i = 1; $i <= 20; $i++) {
            $this->upload($this->admin, $task, 'file.pdf', "doc-$i.pdf")->assertCreated();
        }

        $this->upload($this->admin, $task, 'file.pdf', 'one-too-many.pdf')
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', __('app.task_attachment_limit_reached', ['max' => 20]));

        $this->assertSame(20, Media::query()->count());
        $this->assertCount(20, (array) $this->apiAs($this->admin, $this->alpha)->getJson($this->url($task))->json('attachments'));
    }

    public function test_the_limit_is_per_task(): void
    {
        $one = $this->firmTask();
        $two = $this->firmTask();

        for ($i = 1; $i <= 20; $i++) {
            $this->upload($this->admin, $one, 'file.pdf', "doc-$i.pdf")->assertCreated();
        }

        $this->upload($this->admin, $two, 'file.pdf')->assertCreated();
    }

    public function test_firm_attachment_carries_the_tasks_tenant(): void
    {
        $task = $this->firmTask();
        $this->upload($this->admin, $task, 'photo.jpg')->assertCreated();

        $this->assertSame($this->alpha->id, Media::query()->firstOrFail()->tenant_id);
    }

    public function test_a_foreign_company_cannot_reach_a_firm_attachment(): void
    {
        $task = $this->firmTask();
        $id = $this->upload($this->admin, $task, 'photo.jpg')->json('id');
        $outsider = $this->member($this->beta, 'Admin');

        $this->apiAs($outsider, $this->beta)->getJson($this->url($task))->assertNotFound();
        $this->apiAs($outsider, $this->beta)->get($this->url($task, $id))->assertNotFound();
        $this->apiAs($outsider, $this->beta)->deleteJson($this->url($task, $id))->assertNotFound();
        $this->upload($outsider, $task, 'photo.jpg', as: $this->beta)->assertNotFound();
    }

    public function test_personal_task_attachment_has_no_tenant_and_leaves_no_file_name_in_the_audit(): void
    {
        $task = Task::factory()->global()->createdBy($this->admin)->create();

        $id = $this->upload($this->admin, $task, 'photo.jpg', 'secret-diagnosis.jpg')->assertCreated()->json('id');

        $media = Media::query()->where('uuid', $id)->firstOrFail();
        $this->assertNull($media->tenant_id);

        $activity = Activity::query()->where('event', 'attachment_added')->firstOrFail();
        $this->assertNull($activity->tenant_id);
        $this->assertSame($media->uuid, $this->properties($activity)['attachment_id']);
        $this->assertStringNotContainsString('secret-diagnosis', (string) json_encode($this->properties($activity)));
        $this->assertArrayNotHasKey('name', $this->properties($activity));
    }

    public function test_personal_task_attachment_is_hidden_from_other_users(): void
    {
        $task = Task::factory()->global()->createdBy($this->admin)->create();
        $id = $this->upload($this->admin, $task, 'photo.jpg')->json('id');
        $other = $this->member($this->alpha, 'Admin');

        $this->apiAs($other, $this->alpha)->getJson($this->url($task))->assertNotFound();
        $this->apiAs($other, $this->alpha)->get($this->url($task, $id))->assertNotFound();
        $this->upload($other, $task, 'photo.jpg')->assertNotFound();
    }

    public function test_media_check_constraint_rejects_a_null_tenant_for_other_owners(): void
    {
        $this->expectException(QueryException::class);

        DB::table('media')->insert([
            'uuid' => (string) Str::uuid(), 'model_type' => 'App\Models\Client', 'model_id' => (string) Str::uuid(),
            'collection_name' => 'x', 'name' => 'x', 'file_name' => 'x', 'disk' => 'local', 'size' => 1,
            'manipulations' => '[]', 'custom_properties' => '[]', 'generated_conversions' => '[]', 'responsive_images' => '[]',
            'tenant_id' => null,
        ]);
    }

    public function test_personal_task_creator_deletes_and_the_file_is_gone(): void
    {
        $task = Task::factory()->global()->createdBy($this->admin)->create();
        $id = $this->upload($this->admin, $task, 'photo.jpg')->json('id');
        $path = Media::query()->where('uuid', $id)->firstOrFail()->getPathRelativeToRoot();

        $this->apiAs($this->admin, $this->alpha)->deleteJson($this->url($task, $id))->assertNoContent();

        $this->assertSame(0, Media::query()->count());
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(1, Activity::query()->where('event', 'attachment_deleted')->count());
    }

    public function test_the_uploader_deletes_their_own_file_and_the_audit_row_stays(): void
    {
        $task = $this->firmTask($this->editor);
        $id = $this->upload($this->editor, $task, 'photo.jpg')->json('id');
        $path = Media::query()->where('uuid', $id)->firstOrFail()->getPathRelativeToRoot();

        $this->apiAs($this->editor, $this->alpha)->deleteJson($this->url($task, $id))->assertNoContent();

        Storage::disk('local')->assertMissing($path);
        $audit = Activity::query()->where('event', 'attachment_deleted')->firstOrFail();
        $this->assertSame($this->alpha->id, $audit->tenant_id);
        $this->assertSame('photo.jpg', $this->properties($audit)['name']);
    }

    public function test_a_delete_tasks_holder_deletes_another_users_file(): void
    {
        $task = $this->firmTask($this->editor);
        $id = $this->upload($this->editor, $task, 'photo.jpg')->json('id');

        $this->apiAs($this->admin, $this->alpha)->deleteJson($this->url($task, $id))->assertNoContent();
    }

    public function test_another_editor_without_delete_tasks_cannot_delete_it(): void
    {
        $task = $this->firmTask($this->admin);
        $id = $this->upload($this->admin, $task, 'photo.jpg')->json('id');

        $this->apiAs($this->editor, $this->alpha)->getJson($this->url($task))->assertOk()->assertJsonPath('attachments.0.can_delete', false);
        $this->apiAs($this->editor, $this->alpha)->deleteJson($this->url($task, $id))->assertForbidden();
        $this->assertSame(1, Media::query()->count());
    }

    public function test_the_uploader_who_no_longer_sees_the_task_is_refused(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');
        $task = $this->firmTask($secretary);
        $id = $this->upload($secretary, $task, 'photo.jpg')->json('id');
        $task->forceFill(['created_by_user_id' => $this->admin->id])->save();

        $this->apiAs($secretary, $this->alpha)->deleteJson($this->url($task, $id))->assertForbidden();
        $this->assertSame(1, Media::query()->count());
    }

    public function test_attachments_survive_a_soft_deleted_task_but_return_404(): void
    {
        $task = $this->firmTask();
        $id = $this->upload($this->admin, $task, 'photo.jpg')->json('id');
        $path = Media::query()->where('uuid', $id)->firstOrFail()->getPathRelativeToRoot();

        $task->delete();

        Storage::disk('local')->assertExists($path);
        $this->assertSame(1, Media::query()->count());
        $this->apiAs($this->admin, $this->alpha)->getJson($this->url($task))->assertNotFound();
        $this->apiAs($this->admin, $this->alpha)->get($this->url($task, $id))->assertNotFound();
    }

    public function test_an_attachment_of_another_task_is_a_404(): void
    {
        $one = $this->firmTask();
        $two = $this->firmTask();
        $id = $this->upload($this->admin, $one, 'photo.jpg')->json('id');

        $this->apiAs($this->admin, $this->alpha)->get($this->url($two, $id))->assertNotFound();
        $this->apiAs($this->admin, $this->alpha)->deleteJson($this->url($two, $id))->assertNotFound();
        $this->apiAs($this->admin, $this->alpha)->get($this->url($two, (string) Str::uuid()))->assertNotFound();
    }

    public function test_the_audit_row_for_a_firm_task_carries_name_type_and_size(): void
    {
        $task = $this->firmTask();
        $this->upload($this->admin, $task, 'file.pdf')->assertCreated();

        $audit = Activity::query()->where('event', 'attachment_added')->firstOrFail();
        $this->assertSame($this->alpha->id, $audit->tenant_id);
        $this->assertSame('file.pdf', $this->properties($audit)['name']);
        $this->assertSame('application/pdf', $this->properties($audit)['mime_type']);
    }

    public function test_task_detail_reports_the_attachment_count_only(): void
    {
        $task = $this->firmTask();
        $this->upload($this->admin, $task, 'photo.jpg')->assertCreated();

        $response = $this->apiAs($this->admin, $this->alpha)->getJson('/api/tasks/'.$task->id)->assertOk()->assertJsonPath('attachments_count', 1);

        $this->assertStringNotContainsString('photo.jpg', (string) $response->getContent());
    }

    public function test_uploads_are_rate_limited_per_user(): void
    {
        $task = $this->firmTask();

        for ($i = 0; $i < 60; $i++) {
            $this->apiAs($this->admin, $this->alpha)->postJson($this->url($task), [])->assertUnprocessable();
        }

        $this->apiAs($this->admin, $this->alpha)->postJson($this->url($task), [])->assertTooManyRequests();
    }

    public function test_a_failure_after_the_disk_write_leaves_no_orphaned_file(): void
    {
        $task = $this->firmTask();
        Activity::creating(function (): never {
            throw new RuntimeException('audit down');
        });

        try {
            $this->withoutExceptionHandling()->upload($this->admin, $task, 'photo.jpg');
            $this->fail('Expected the upload to throw.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit down', $e->getMessage());
        }

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_list_query_count_does_not_grow_with_the_number_of_attachments(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');
        $task = $this->firmTask($secretary);
        $this->upload($secretary, $task, 'photo.jpg')->assertCreated();

        $count = function () use ($secretary, $task): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->apiAs($secretary, $this->alpha)->getJson($this->url($task))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $count();
        $one = $count();

        for ($i = 0; $i < 5; $i++) {
            $this->upload($secretary, $task, 'file.pdf', "doc-$i.pdf")->assertCreated();
        }

        $this->assertSame($one, $count());
    }
}
