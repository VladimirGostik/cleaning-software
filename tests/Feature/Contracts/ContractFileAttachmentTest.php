<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use LogicException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\BuildsContractFiles;
use Tests\TestCase;

final class ContractFileAttachmentTest extends TestCase
{
    use BuildsContractFiles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $this->tenant);
        $object = CleaningObject::factory()->create(['tenant_id' => $this->tenant->id]);

        /** @var string $uuid */
        $uuid = $this->postJson('/uploads', ['file' => $this->contractPdf('main.pdf', 'main')])->json('uuid');
        $this->post(route('contracts.store-file'), [
            'title' => 'Zmluva', 'number' => null, 'category' => 'service_agreement',
            'contractable_type' => 'cleaning_object', 'contractable_id' => $object->id,
            'valid_from' => '2026-01-01', 'term_type' => 'indefinite', 'end_date' => null,
            'is_signed' => true, 'signed_on' => null, 'notes' => null, 'file_uuid' => $uuid,
        ])->assertSessionDoesntHaveErrors();
        $this->contract = Contract::query()->where('source', 'file')->firstOrFail();
    }

    /** @return TestResponse<Response> */
    private function attach(UploadedFile $file, ?string $title = null): TestResponse
    {
        return $this->post(route('contracts.attachments.store', $this->contract), ['file' => $file, 'title' => $title]);
    }

    private function document(): Document
    {
        return $this->contract->fresh()->document ?? throw new LogicException;
    }

    public function test_adds_an_attachment_with_a_title_and_lists_it_on_show(): void
    {
        $this->attach($this->contractPdf('cenovka.pdf', 'price'), 'Cenová ponuka 2027')->assertSessionHas('success');

        $media = $this->document()->getMedia('attachments')->sole();
        $this->assertSame('Cenová ponuka 2027', $media->name);
        $this->assertSame('cenovka.pdf', $media->getCustomProperty('original_name'));
        $this->assertSame(hash('sha256', "%PDF-1.4\nprice"), $media->getCustomProperty('sha256'));
        $this->assertSame(1, Activity::query()->where('event', 'attachment_added')->count());

        $this->get(route('contracts.show', $this->contract))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('contract.attachments', 1)
            ->where('contract.attachments.0.title', 'Cenová ponuka 2027')
            ->where('contract.attachments.0.can_delete', true)
            ->where('contract.attachments.0.file_name', 'cenovka.pdf'));
    }

    public function test_title_defaults_to_the_cleaned_file_name(): void
    {
        $this->attach($this->contractPdf('dodatok.pdf', 'x'))->assertSessionHas('success');

        $this->assertSame('dodatok.pdf', $this->document()->getMedia('attachments')->sole()->name);
    }

    public function test_the_same_file_again_returns_the_existing_row(): void
    {
        $this->attach($this->contractPdf('a.pdf', 'dup'))->assertSessionHas('success');
        $this->attach($this->contractPdf('b.pdf', 'dup'))->assertSessionHas('info');

        $this->assertCount(1, $this->document()->getMedia('attachments'));
    }

    public function test_the_main_file_cannot_be_added_again_as_an_attachment(): void
    {
        $this->attach($this->contractPdf('copy.pdf', 'main'))->assertSessionHas('info');

        $this->assertCount(0, $this->document()->getMedia('attachments'));
    }

    public function test_the_twenty_first_attachment_is_refused(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->attach($this->contractPdf("f{$i}.pdf", "c{$i}"))->assertSessionDoesntHaveErrors();
        }

        $this->attach($this->contractPdf('f20.pdf', 'c20'))->assertSessionHasErrors('file');

        $this->assertCount(20, $this->document()->getMedia('attachments'));
    }

    public function test_a_disallowed_type_is_refused(): void
    {
        $this->attach($this->contractFixture('page.html'))->assertSessionHasErrors('file');
        $this->attach($this->contractFixture('file.xlsx'))->assertSessionHasErrors('file');
    }

    public function test_deletes_an_attachment_and_never_the_main_file(): void
    {
        $this->attach($this->contractPdf('a.pdf', 'del'));
        $attachment = $this->document()->getMedia('attachments')->sole();
        $main = $this->document()->getFirstMedia('file');
        $this->assertNotNull($main);

        $this->delete(route('contracts.attachments.destroy', [$this->contract, $main->uuid]))->assertNotFound();
        $this->assertNotNull($this->document()->getFirstMedia('file'));

        $this->delete(route('contracts.attachments.destroy', [$this->contract, $attachment->uuid]))->assertSessionHas('success');
        $this->assertCount(0, $this->document()->getMedia('attachments'));
        $this->assertSame(1, Activity::query()->where('event', 'attachment_deleted')->count());
    }

    public function test_downloads_pdf_inline_and_docx_as_attachment(): void
    {
        $this->attach($this->contractFixture('file.docx', 'dodatok.docx'), 'Dodatok');
        $main = $this->document()->getFirstMedia('file');
        $docx = $this->document()->getMedia('attachments')->sole();
        $this->assertNotNull($main);

        $pdf = $this->get(route('contracts.files.show', [$this->contract, $main->uuid]))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', (string) $pdf->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $pdf->headers->get('X-Content-Type-Options'));

        $word = $this->get(route('contracts.files.show', [$this->contract, $docx->uuid]))->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $word->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment', (string) $word->headers->get('Content-Disposition'));
        $this->assertStringContainsString('dodatok.docx', (string) $word->headers->get('Content-Disposition'));
    }

    public function test_a_foreign_uuid_is_not_found(): void
    {
        $this->get(route('contracts.files.show', [$this->contract, fake()->uuid()]))->assertNotFound();
    }

    public function test_the_pdf_route_is_denied_for_a_file_contract(): void
    {
        $this->get(route('contracts.pdf', $this->contract))->assertForbidden();
    }

    public function test_view_only_user_can_download_but_not_add_or_delete(): void
    {
        $this->attach($this->contractPdf('a.pdf', 'v'));
        $attachment = $this->document()->getMedia('attachments')->sole();

        $this->actingAsTenantUser('Účtovníčka', $this->tenant);

        $this->get(route('contracts.files.show', [$this->contract, $attachment->uuid]))->assertOk();
        $this->attach($this->contractPdf('b.pdf', 'w'))->assertForbidden();
        $this->delete(route('contracts.attachments.destroy', [$this->contract, $attachment->uuid]))->assertForbidden();
        $this->get(route('contracts.show', $this->contract))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contract.can_manage_files', false)
            ->where('contract.attachments.0.can_delete', false)
            ->where('fileConstraints', null));
        $this->assertCount(1, $this->document()->getMedia('attachments'));
    }

    public function test_generated_contracts_take_attachments_too(): void
    {
        $generated = Contract::factory()->active()->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('contracts.attachments.store', $generated), ['file' => $this->contractPdf('d.pdf', 'gen')])->assertSessionHas('success');

        $this->assertCount(1, $generated->fresh()?->document?->getMedia('attachments') ?? []);
    }

    public function test_attachment_routes_of_another_firm_answer_404(): void
    {
        $other = Tenant::factory()->create();
        $foreign = Contract::factory()->fromFile()->create(['tenant_id' => $other->id]);
        $uuid = fake()->uuid();

        $this->post(route('contracts.attachments.store', $foreign), ['file' => $this->contractPdf('x.pdf', 'x')])->assertNotFound();
        $this->delete(route('contracts.attachments.destroy', [$foreign, $uuid]))->assertNotFound();
        $this->get(route('contracts.files.show', [$foreign, $uuid]))->assertNotFound();
    }
}
