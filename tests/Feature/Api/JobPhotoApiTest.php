<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\JobStatusEnum;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesWorkRecordFixtures;
use Tests\TestCase;

final class JobPhotoApiTest extends TestCase
{
    use CreatesWorkRecordFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        Storage::fake('local');
        config(['scheduling.work_records.photos.disk' => 'local']);
    }

    /** @return array{0: Tenant, 1: User, 2: ScheduledJob} */
    private function record(): array
    {
        $firm = $this->firm();
        $user = $this->member($firm, 'Interná upratovačka');
        $object = $this->objectIn($firm);
        $items = array_values($this->itemsFor($object));
        $this->assignedJob($user, $firm, $object);
        $id = $this->apiAs($user, $firm)->postJson('/api/work-records', $this->recordPayload($object, $items))->json('id');

        return [$firm, $user, ScheduledJob::query()->whereKey($id)->firstOrFail()];
    }

    /** @return TestResponse<Response> */
    private function upload(User $user, Tenant $firm, ScheduledJob $job, ?UploadedFile $file = null, ?string $uuid = null): TestResponse
    {
        return $this->apiAs($user, $firm)->post("/api/jobs/{$job->id}/photos", [
            'file' => $file ?? UploadedFile::fake()->image('foto.jpg', 600, 400),
            'client_uuid' => $uuid ?? (string) Str::uuid(),
        ], ['Accept' => 'application/json']);
    }

    public function test_assignee_adds_up_to_five_photos_and_the_sixth_is_refused(): void
    {
        [$firm, $user, $job] = $this->record();

        for ($i = 0; $i < 5; $i++) {
            $this->upload($user, $firm, $job)->assertCreated()->assertJsonStructure(['id', 'name', 'mime_type', 'size'])->assertJsonPath('mime_type', 'image/jpeg');
        }

        $this->upload($user, $firm, $job)->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertCount(5, $this->reload($job)->getMedia(ScheduledJob::PHOTOS_COLLECTION));
        $this->assertSame($firm->id, $this->reload($job)->getFirstMedia(ScheduledJob::PHOTOS_COLLECTION)?->tenant_id);
    }

    public function test_the_same_photo_client_uuid_stores_one_file(): void
    {
        [$firm, $user, $job] = $this->record();
        $uuid = (string) Str::uuid();

        $first = $this->upload($user, $firm, $job, uuid: $uuid)->assertCreated();
        $second = $this->upload($user, $firm, $job, uuid: $uuid)->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertCount(1, $this->reload($job)->getMedia(ScheduledJob::PHOTOS_COLLECTION));
    }

    public function test_a_replay_still_answers_when_the_limit_is_reached(): void
    {
        [$firm, $user, $job] = $this->record();
        $uuid = (string) Str::uuid();
        $this->upload($user, $firm, $job, uuid: $uuid)->assertCreated();
        for ($i = 0; $i < 4; $i++) {
            $this->upload($user, $firm, $job)->assertCreated();
        }

        $this->upload($user, $firm, $job, uuid: $uuid)->assertOk();
    }

    public function test_pdf_oversized_and_missing_files_are_refused(): void
    {
        [$firm, $user, $job] = $this->record();

        $this->upload($user, $firm, $job, UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload($user, $firm, $job, UploadedFile::fake()->image('big.jpg')->size(10241))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->apiAs($user, $firm)->postJson("/api/jobs/{$job->id}/photos", ['client_uuid' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->apiAs($user, $firm)->post("/api/jobs/{$job->id}/photos", ['file' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('client_uuid');
        $this->assertCount(0, $this->reload($job)->getMedia(ScheduledJob::PHOTOS_COLLECTION));
    }

    public function test_another_cleaner_and_a_non_record_job_are_forbidden(): void
    {
        [$firm, $user, $job] = $this->record();
        $other = $this->member($firm, 'Interná upratovačka');
        $regular = $this->assignedJob($user, $firm, $this->objectOfJob($job), JobStatusEnum::Planned);

        $this->upload($other, $firm, $job)->assertForbidden();
        $this->upload($user, $firm, $regular)->assertForbidden();
        $this->assertCount(0, $this->reload($job)->getMedia(ScheduledJob::PHOTOS_COLLECTION));
    }

    public function test_a_record_older_than_seven_days_is_forbidden(): void
    {
        [$firm, $user, $job] = $this->record();
        $this->upload($user, $firm, $job)->assertCreated();

        $this->travel(7 * 24 * 60 - 5)->minutes();
        $this->upload($user, $firm, $job)->assertCreated();

        $this->travel(10)->minutes();
        $this->upload($user, $firm, $job)->assertForbidden();
    }

    public function test_a_job_of_another_firm_is_not_found(): void
    {
        [$firm, $user, $job] = $this->record();
        $b = $this->firm('B');
        $this->member($b, 'Interná upratovačka', $user);

        $this->upload($user, $b, $job)->assertNotFound();
    }

    public function test_the_photo_streams_inline_on_the_web_for_a_viewer_and_is_gone_with_the_record(): void
    {
        [$firm, $user, $job] = $this->record();
        $photoId = $this->upload($user, $firm, $job)->json('id');
        $admin = $this->member($firm, 'Admin');

        $response = $this->webAs($admin, $firm)->get(route('jobs.photos.show', ['job' => $job->id, 'photo' => $photoId]));
        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->webAs($admin, $firm)->get(route('jobs.photos.show', ['job' => $job->id, 'photo' => (string) Str::uuid()]))->assertNotFound();

        $job->delete();
        $this->webAs($admin, $firm)->get(route('jobs.photos.show', ['job' => $job->id, 'photo' => $photoId]))->assertNotFound();
    }

    public function test_a_user_who_cannot_view_the_job_cannot_open_the_photo(): void
    {
        [$firm, $user, $job] = $this->record();
        $photoId = $this->upload($user, $firm, $job)->json('id');
        $other = $this->member($firm, 'Interná upratovačka');

        $this->webAs($other, $firm)->get(route('jobs.photos.show', ['job' => $job->id, 'photo' => $photoId]))->assertForbidden();
    }

    public function test_office_roles_that_see_the_overview_may_open_the_photo_and_a_cleaner_of_another_object_may_not(): void
    {
        [$firm, $user, $job] = $this->record();
        $photoId = $this->upload($user, $firm, $job)->json('id');
        $route = route('jobs.photos.show', ['job' => $job->id, 'photo' => $photoId]);

        foreach (['Admin', 'Sekretárka', 'Účtovníčka'] as $role) {
            $this->webAs($this->member($firm, $role), $firm)->get($route)->assertOk();
        }

        $other = $this->member($firm, 'Interná upratovačka');
        $this->assignedJob($other, $firm, $this->objectIn($firm, 'Elsewhere'));
        $this->webAs($other, $firm)->get($route)->assertForbidden();
    }

    public function test_a_photo_of_one_job_requested_through_another_job_is_not_found(): void
    {
        [$firm, $user, $jobA] = $this->record();
        $photoId = $this->upload($user, $firm, $jobA)->json('id');
        $object = $this->objectOfJob($jobA);
        $jobB = $this->assignedJob($user, $firm, $object);

        $this->webAs($this->member($firm, 'Admin'), $firm)->get(route('jobs.photos.show', ['job' => $jobB->id, 'photo' => $photoId]))->assertNotFound();
    }
}
