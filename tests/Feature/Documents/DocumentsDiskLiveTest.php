<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Contracts\RendersContractPdf;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\Pdf\ContractPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Runs only against a real S3-compatible bucket: LIVE_DOCUMENTS_DISK=s3 plus AWS_* variables. */
final class DocumentsDiskLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $disk = getenv('LIVE_DOCUMENTS_DISK');

        if (! is_string($disk) || $disk === '') {
            $this->markTestSkipped('LIVE_DOCUMENTS_DISK is not set.');
        }

        config(['documents.disk' => $disk]);
    }

    public function test_employee_file_and_signed_contract_pdf_round_trip_on_the_live_disk(): void
    {
        $disk = Storage::disk(config()->string('documents.disk'));
        $tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $tenant);
        $employee = TenantMembership::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('employees.documents.store', $employee), [
            'type' => 'employee_id_card',
            'file' => UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\nlive-probe"),
        ])->assertSessionHasNoErrors();
        $document = Document::query()->latest()->firstOrFail();
        $file = $document->getFirstMedia('file');
        $this->assertNotNull($file);
        $this->assertTrue($disk->exists($file->getPathRelativeToRoot()));
        $this->get(route('employees.documents.download', [$employee, $document]))->assertOk()->assertDownload('id.pdf');

        $this->app->singleton(RendersContractPdf::class, ContractPdfService::class);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $contract = Contract::factory()->forClient($client)->withBody('<p>Live probe 900101/1234</p>')->create(['tenant_id' => $tenant->id]);
        $this->post(route('contracts.sign', $contract))->assertSessionHasNoErrors();
        $pdf = $contract->fresh()?->document?->getFirstMedia('pdf');
        $this->assertNotNull($pdf);
        $stored = (string) $disk->get($pdf->getPathRelativeToRoot());
        $this->assertStringNotContainsString('%PDF', substr($stored, 0, 4));
        $this->assertStringNotContainsString('900101/1234', $stored);
        $download = $this->get(route('contracts.pdf', $contract))->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $download->getContent());

        $this->delete(route('employees.documents.destroy', [$employee, $document]));
        $contract->fresh()?->document?->forceDelete();
        $this->assertFalse($disk->exists($file->getPathRelativeToRoot()));
        $this->assertFalse($disk->exists($pdf->getPathRelativeToRoot()));
    }
}
