<?php

declare(strict_types=1);

namespace Tests\Feature\Employees;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class EmployeeDocumentTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = (string) config('documents.disk');
        Storage::fake($this->disk);
        Storage::fake('public');
    }

    private function pdf(string $name = 'obciansky.pdf', int $kb = 1): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".str_repeat('A', $kb * 1024));
    }

    /** @return array{Tenant, TenantMembership} */
    private function admin(): array
    {
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);

        return [$tenant, TenantMembership::factory()->create(['tenant_id' => $tenant->id])];
    }

    private function upload(TenantMembership $employee, ?UploadedFile $file = null, array $extra = []): Document
    {
        $this->post(route('employees.documents.store', $employee), array_merge([
            'type' => 'employee_id_card',
            'file' => $file ?? $this->pdf(),
        ], $extra))->assertSessionHasNoErrors();

        return Document::query()->latest()->firstOrFail();
    }

    public function test_upload_stores_the_file_on_the_private_documents_disk_only(): void
    {
        [, $employee] = $this->admin();

        $document = $this->upload($employee);

        $media = $document->getFirstMedia('file');
        $this->assertNotNull($media);
        $this->assertSame($this->disk, $media->disk);
        Storage::disk($this->disk)->assertExists($media->getPathRelativeToRoot());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame('obciansky.pdf', $document->title);
        $this->assertSame($employee->id, $document->documentable_id);
    }

    public function test_download_streams_the_file_as_an_attachment_and_is_audited(): void
    {
        [, $employee] = $this->admin();
        $document = $this->upload($employee);

        $response = $this->get(route('employees.documents.download', [$employee, $document]));

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
        $this->assertDatabaseHas('activity_log', ['event' => 'downloaded', 'subject_id' => $document->id]);
    }

    public function test_delete_is_hard_and_removes_the_file(): void
    {
        [, $employee] = $this->admin();
        $document = $this->upload($employee);
        $path = $document->getFirstMedia('file')?->getPathRelativeToRoot() ?? '';

        $this->delete(route('employees.documents.destroy', [$employee, $document]))->assertRedirect();

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        Storage::disk($this->disk)->assertMissing($path);
    }

    public function test_document_of_another_employee_or_firm_is_not_found(): void
    {
        [$tenant, $employee] = $this->admin();
        $other = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        $document = $this->upload($other);

        $this->get(route('employees.documents.download', [$employee, $document]))->assertNotFound();
        $this->delete(route('employees.documents.destroy', [$employee, $document]))->assertNotFound();

        $foreignTenant = Tenant::factory()->create();
        $foreignEmployee = TenantMembership::factory()->create(['tenant_id' => $foreignTenant->id]);
        $foreignDocument = Document::factory()->forEmployee($foreignEmployee)->create();

        $this->get(route('employees.documents.download', [$foreignEmployee, $foreignDocument]))->assertNotFound();
        $this->post(route('employees.documents.store', $foreignEmployee), ['type' => 'employee_id_card', 'file' => $this->pdf()])->assertForbidden();
    }

    public function test_cleaner_and_roles_without_the_permissions_are_refused(): void
    {
        $tenant = Tenant::factory()->create();
        $employee = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->forEmployee($employee)->create();
        $this->actingAsTenantUser('Interná upratovačka', $tenant);

        $this->post(route('employees.documents.store', $employee), ['type' => 'employee_id_card', 'file' => $this->pdf()])->assertForbidden();
        $this->get(route('employees.documents.download', [$employee, $document]))->assertForbidden();
        $this->delete(route('employees.documents.destroy', [$employee, $document]))->assertForbidden();
    }

    public function test_vedouca_can_view_employees_but_not_their_files(): void
    {
        $tenant = Tenant::factory()->create();
        $employee = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);
        Document::factory()->forEmployee($employee)->create();
        $this->actingAsTenantUser('Vedúca', $tenant);

        $this->get(route('employees.show', $employee))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('documents', null)
            ->where('documentConstraints', null));
    }

    public function test_show_lists_documents_and_constraints_for_admin(): void
    {
        [, $employee] = $this->admin();
        $this->upload($employee);

        $this->get(route('employees.show', $employee))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('documents', 1)
            ->where('documents.0.type', 'employee_id_card')
            ->has('documents.0.download_url')
            ->where('documentConstraints.max_size_kb', 10240)
            ->where('employee.can.manage_documents', true));
    }

    public function test_executable_renamed_to_pdf_is_refused(): void
    {
        [, $employee] = $this->admin();
        $path = tempnam(sys_get_temp_dir(), 'exe');
        file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00".str_repeat("\x00", 512));
        $exe = new UploadedFile($path, 'virus.pdf', null, null, true);

        $this->post(route('employees.documents.store', $employee), ['type' => 'employee_id_card', 'file' => $exe])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_over_limit_file_and_non_employee_type_are_refused(): void
    {
        [, $employee] = $this->admin();

        $this->post(route('employees.documents.store', $employee), ['type' => 'employee_id_card', 'file' => $this->pdf('big.pdf', 10241)])
            ->assertSessionHasErrors('file');
        $this->post(route('employees.documents.store', $employee), ['type' => 'nda', 'file' => $this->pdf()])
            ->assertSessionHasErrors('type');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_employee_files_are_absent_from_the_media_viewer(): void
    {
        [, $employee] = $this->admin();
        $this->upload($employee);
        $media = Document::query()->firstOrFail()->getFirstMedia('file');
        $this->assertNotNull($media);

        $this->get(route('media.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('media.data', 0));
        $this->get(route('media.show', $media->id))->assertForbidden();
    }
}
