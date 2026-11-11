<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Enums\ContractSourceEnum;
use App\Enums\ContractStatusEnum;
use App\Events\ContractSigned;
use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\WorkBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsContractFiles;
use Tests\TestCase;

final class ContractFileTest extends TestCase
{
    use BuildsContractFiles;
    use RefreshDatabase;

    private Tenant $tenant;

    private CleaningObject $object;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->tenant = Tenant::factory()->create();
        $this->actingAsTenantUser('Admin', $this->tenant);
        $this->object = CleaningObject::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function stage(UploadedFile $file): string
    {
        /** @var string $uuid */
        $uuid = $this->postJson('/uploads', ['file' => $file])->json('uuid');

        return $uuid;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(?string $uuid, array $overrides = []): array
    {
        return [
            'title' => 'Zmluva o upratovaní',
            'number' => null,
            'category' => 'service_agreement',
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $this->object->id,
            'valid_from' => '2026-01-01',
            'term_type' => 'fixed',
            'end_date' => now()->addYear()->toDateString(),
            'is_signed' => true,
            'signed_on' => '2026-01-02',
            'notes' => null,
            'file_uuid' => $uuid,
            ...$overrides,
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    private function createContract(?UploadedFile $file = null, array $overrides = []): Contract
    {
        $this->post(route('contracts.store-file'), $this->payload($this->stage($file ?? $this->contractPdf()), $overrides))->assertSessionDoesntHaveErrors();

        return Contract::query()->where('source', ContractSourceEnum::File->value)->orderByDesc('id')->firstOrFail();
    }

    public function test_creates_a_signed_file_contract_with_the_entered_date(): void
    {
        Event::fake([ContractSigned::class]);
        $pdf = $this->contractPdf();
        $hash = hash_file('sha256', $pdf->getRealPath());

        $contract = $this->createContract($pdf);

        $this->assertSame(ContractSourceEnum::File, $contract->source);
        $this->assertSame(ContractStatusEnum::Active, $contract->status);
        $this->assertTrue($contract->is_signed);
        $this->assertSame('2026-01-02', $contract->signed_at?->toDateString());
        $this->assertSame($hash, $contract->file_sha256);
        $this->assertNull($contract->document?->body);
        $this->assertNotNull($contract->document?->getFirstMedia('file'));
        $this->assertNull($contract->document->getFirstMedia('pdf'));
        $this->assertSame($hash, $contract->document->getFirstMedia('file')->getCustomProperty('sha256'));
        Event::assertNotDispatched(ContractSigned::class);
        $this->assertSame(0, WorkBreakdown::query()->count());
    }

    public function test_signed_without_date_and_unsigned_draft_are_kept_as_entered(): void
    {
        $unknown = $this->createContract($this->contractPdf('a.pdf', 'a'), ['signed_on' => null]);
        $draft = $this->createContract($this->contractPdf('b.pdf', 'b'), ['is_signed' => false, 'signed_on' => null]);

        $this->assertTrue($unknown->is_signed);
        $this->assertNull($unknown->signed_at);
        $this->assertFalse($draft->is_signed);
        $this->assertNull($draft->signed_at);
        $this->assertSame(ContractStatusEnum::Active, $draft->status);
    }

    public function test_signed_on_without_is_signed_is_rejected(): void
    {
        $this->post(route('contracts.store-file'), $this->payload($this->stage($this->contractPdf()), ['is_signed' => false, 'signed_on' => '2026-01-02']))
            ->assertSessionHasErrors('signed_on');

        $this->assertSame(0, Contract::query()->count());
    }

    public function test_signed_on_in_the_future_is_rejected(): void
    {
        $this->post(route('contracts.store-file'), $this->payload($this->stage($this->contractPdf()), ['signed_on' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('signed_on');
    }

    public function test_fixed_term_in_the_past_is_expired_at_create_and_the_cron_stays_silent(): void
    {
        $contract = $this->createContract(null, ['end_date' => now()->subDay()->toDateString()]);

        $this->assertSame(ContractStatusEnum::Expired, $contract->status);
    }

    public function test_indefinite_term_clears_the_end_date(): void
    {
        $contract = $this->createContract(null, ['term_type' => 'indefinite', 'end_date' => null]);

        $this->assertNull($contract->end_date);
        $this->assertSame(ContractStatusEnum::Active, $contract->status);
    }

    public function test_fixed_term_requires_an_end_date(): void
    {
        $this->post(route('contracts.store-file'), $this->payload($this->stage($this->contractPdf()), ['end_date' => null]))
            ->assertSessionHasErrors('end_date');
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function fileTypes(): array
    {
        return [
            'pdf' => ['pdf', 'application/pdf'],
            'doc' => ['file.doc', 'application/msword'],
            'docx' => ['file.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'odt' => ['odt', 'application/vnd.oasis.opendocument.text'],
            'jpg' => ['photo.jpg', 'image/jpeg'],
            'png' => ['photo.png', 'image/png'],
        ];
    }

    #[DataProvider('fileTypes')]
    public function test_accepts_every_allowed_file_type(string $source, string $mime): void
    {
        $file = match ($source) {
            'pdf' => $this->contractPdf(),
            'odt' => $this->contractOdt(),
            default => $this->contractFixture($source),
        };

        $contract = $this->createContract($file);

        $this->assertSame($mime, $contract->document?->getFirstMedia('file')?->mime_type);
    }

    public function test_refuses_a_disallowed_type_and_a_missing_file(): void
    {
        $this->post(route('contracts.store-file'), $this->payload($this->stage($this->contractFixture('page.html'))))->assertSessionHasErrors('file_uuid');
        $this->post(route('contracts.store-file'), $this->payload($this->stage($this->contractFixture('image.svg'))))->assertSessionHasErrors('file_uuid');
        $this->post(route('contracts.store-file'), $this->payload(null))->assertSessionHasErrors('file_uuid');

        $this->assertSame(0, Contract::query()->count());
    }

    public function test_employment_category_and_employee_subject_are_refused(): void
    {
        $membership = TenantMembership::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('contracts.store-file'), $this->payload($this->stage($this->contractPdf()), ['category' => 'employment']))->assertSessionHasErrors('category');
        $this->post(route('contracts.store-file'), $this->payload($this->stage($this->contractPdf()), [
            'contractable_type' => 'tenant_membership',
            'contractable_id' => $membership->id,
        ]))->assertSessionHasErrors('contractable_type');
    }

    public function test_an_object_of_another_tenant_is_refused_on_the_subject(): void
    {
        $foreign = CleaningObject::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->post(route('contracts.store-file'), $this->payload($this->stage($this->contractPdf()), ['contractable_id' => $foreign->id]))
            ->assertSessionHasErrors('contractable_id');
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_the_same_main_file_twice_is_refused_and_a_deleted_one_does_not_block(): void
    {
        $first = $this->createContract($this->contractPdf('a.pdf', 'same'));

        $this->post(route('contracts.store-file'), $this->payload($this->stage($this->contractPdf('b.pdf', 'same'))))->assertSessionHasErrors('file_uuid');
        $this->assertSame(1, Contract::query()->count());

        $this->delete(route('contracts.destroy', $first))->assertRedirect();
        $this->post(route('contracts.store-file'), $this->payload($this->stage($this->contractPdf('c.pdf', 'same'))))->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Contract::query()->count());
    }

    public function test_update_changes_details_and_recomputes_status(): void
    {
        $contract = $this->createContract();

        $this->put(route('contracts.update-file', $contract), $this->payload(null, [
            'title' => 'Dodatok',
            'end_date' => now()->subDays(3)->toDateString(),
            'is_signed' => false,
            'signed_on' => null,
        ]))->assertSessionDoesntHaveErrors()->assertRedirect(route('contracts.show', $contract));

        $contract->refresh();
        $this->assertSame('Dodatok', $contract->title);
        $this->assertSame(ContractStatusEnum::Expired, $contract->status);
        $this->assertFalse($contract->is_signed);
        $this->assertNull($contract->signed_at);

        $this->put(route('contracts.update-file', $contract), $this->payload(null, ['end_date' => now()->addYear()->toDateString()]))->assertSessionDoesntHaveErrors();
        $this->assertSame(ContractStatusEnum::Active, $contract->fresh()?->status);
    }

    public function test_a_terminated_file_contract_stays_terminated_on_edit(): void
    {
        $contract = $this->createContract();
        $contract->update(['status' => ContractStatusEnum::Terminated, 'terminated_at' => now()]);

        $this->put(route('contracts.update-file', $contract), $this->payload(null, ['title' => 'Nový názov']))->assertSessionDoesntHaveErrors();

        $contract->refresh();
        $this->assertSame(ContractStatusEnum::Terminated, $contract->status);
        $this->assertSame('Nový názov', $contract->title);
    }

    public function test_replacing_the_main_file_updates_hash_drops_the_old_media_and_logs(): void
    {
        Event::fake([ContractSigned::class]);
        $old = $this->contractPdf('old.pdf', 'old');
        $contract = $this->createContract($old);
        $oldHash = (string) $contract->file_sha256;
        $new = $this->contractPdf('new.pdf', 'new');
        $newHash = (string) hash_file('sha256', $new->getRealPath());

        $this->put(route('contracts.update-file', $contract), $this->payload($this->stage($new)))->assertSessionDoesntHaveErrors();

        $contract->refresh();
        $media = $contract->document?->getMedia('file');
        $this->assertCount(1, $media ?? []);
        $this->assertSame($newHash, $contract->file_sha256);
        $this->assertSame($newHash, $media?->first()?->getCustomProperty('sha256'));

        $log = Activity::query()->where('event', 'main_file_replaced')->sole();
        $this->assertSame('old.pdf', data_get($log->properties, 'old_name'));
        $this->assertSame($oldHash, data_get($log->properties, 'old_sha256'));
        $this->assertSame('new.pdf', data_get($log->properties, 'new_name'));
        $this->assertSame($newHash, data_get($log->properties, 'new_sha256'));
        Event::assertNotDispatched(ContractSigned::class);
    }

    public function test_replacing_with_a_file_that_is_on_another_contract_is_refused(): void
    {
        $this->createContract($this->contractPdf('a.pdf', 'aaa'));
        $second = $this->createContract($this->contractPdf('b.pdf', 'bbb'));
        $hash = $second->file_sha256;

        $this->put(route('contracts.update-file', $second), $this->payload($this->stage($this->contractPdf('a2.pdf', 'aaa'))))->assertSessionHasErrors('file_uuid');

        $this->assertSame($hash, $second->fresh()?->file_sha256);
    }

    public function test_the_update_file_route_refuses_generated_contracts(): void
    {
        $active = Contract::factory()->active()->create(['tenant_id' => $this->tenant->id]);
        $draft = Contract::factory()->draft()->create(['tenant_id' => $this->tenant->id]);

        $this->put(route('contracts.update-file', $active), $this->payload(null))->assertForbidden();
        $this->put(route('contracts.update-file', $draft), $this->payload(null))->assertSessionHasErrors('status');
    }

    public function test_generated_contracts_keep_the_legal_record_rule_and_file_contracts_are_deletable(): void
    {
        $active = Contract::factory()->active()->create(['tenant_id' => $this->tenant->id]);
        $this->delete(route('contracts.destroy', $active))->assertForbidden();

        $file = $this->createContract();
        $this->delete(route('contracts.destroy', $file))->assertRedirect(route('contracts.index'));
        $this->assertSoftDeleted($file);
    }

    public function test_detail_and_form_context_expose_the_file_data(): void
    {
        $contract = $this->createContract();

        $this->get(route('contracts.show', $contract))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contract.source', 'file')
            ->where('contract.is_signed', true)
            ->where('contract.is_editable', true)
            ->where('contract.can_be_signed', false)
            ->where('contract.can_download_pdf', false)
            ->where('contract.can_manage_files', true)
            ->where('contract.body', null)
            ->where('contract.main_file.can_delete', false)
            ->has('fileConstraints.allowed_mimes'));

        $this->get(route('contracts.create'))->assertInertia(fn (AssertableInertia $page) => $page->where('context.file_constraints.max_count', 20));
        $this->get(route('contracts.edit', $contract))->assertOk();
    }

    public function test_active_file_contract_counts_on_the_dashboard_and_raises_the_expiry_alert(): void
    {
        $this->createContract(null, ['end_date' => now()->addDays(10)->toDateString()]);

        /** @var array{companies: list<array{contracts: array{active: int, expiring_30d: int}}>, alerts: list<array{type: string}>} $overview */
        $overview = $this->get(route('dashboard'))->inertiaProps('overview');

        $this->assertSame(1, $overview['companies'][0]['contracts']['active']);
        $this->assertSame(1, $overview['companies'][0]['contracts']['expiring_30d']);
        $this->assertContains('contract_expiring', array_column($overview['alerts'], 'type'));
    }
}
