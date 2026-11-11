<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Activity;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\Guard\PromptGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\BuildsContractFiles;
use Tests\Support\CreatesTaskFixtures;
use Tests\TestCase;

final class TokenContractUploadTest extends TestCase
{
    use BuildsContractFiles;
    use CreatesTaskFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private User $admin;

    private CleaningObject $object;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->admin = $this->member($this->alpha, 'Admin');
        $this->object = CleaningObject::factory()->create([
            'tenant_id' => $this->alpha->id,
            'client_id' => Client::factory()->create(['tenant_id' => $this->alpha->id])->id,
        ]);
        config()->set('token_uploads.enabled', true);
        $this->token = $this->admin->createToken('claude', ['mcp'])->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    private function send(string $path, array $data, ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->post('/api/token/'.$path, $data, ['Accept' => 'application/json']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function body(string $content = 'one', array $overrides = []): array
    {
        return [
            'company_id' => $this->alpha->id,
            'title' => 'Zmluva z mailu',
            'category' => 'service_agreement',
            'contractable_type' => 'cleaning_object',
            'contractable_id' => $this->object->id,
            'valid_from' => '2025-03-01',
            'term_type' => 'indefinite',
            'is_signed' => 1,
            'notes' => 'Dátum z mailu',
            'file' => $this->contractPdf('zmluva.pdf', $content),
            ...$overrides,
        ];
    }

    public function test_creates_a_contract_and_a_repeat_changes_nothing(): void
    {
        $first = $this->send('contracts', $this->body())->assertCreated();

        $this->assertTrue($first->json('created'));
        $this->assertNull($first->json('attachment_id'));
        $this->assertSame(route('contracts.show', ['contract' => $first->json('contract_id')]), $first->json('url'));
        $contract = Contract::query()->sole();
        $this->assertSame('file', $contract->source->value);
        $this->assertTrue($contract->is_signed);
        $this->assertNull($contract->signed_at);
        $this->assertNotNull($contract->document?->getFirstMedia('file'));

        $again = $this->send('contracts', $this->body('one', ['title' => 'Iný názov']))->assertOk();

        $this->assertFalse($again->json('created'));
        $this->assertSame($first->json('contract_id'), $again->json('contract_id'));
        $this->assertSame(1, Contract::query()->count());
        $this->assertSame('Zmluva z mailu', $contract->fresh()?->title);
    }

    public function test_after_the_first_is_soft_deleted_the_same_file_creates_a_new_contract(): void
    {
        $this->send('contracts', $this->body())->assertCreated();
        Contract::query()->sole()->delete();

        $this->send('contracts', $this->body())->assertCreated();

        $this->assertSame(1, Contract::query()->count());
        $this->assertSame(2, Contract::query()->withTrashed()->count());
    }

    public function test_an_unsigned_draft_and_an_expired_term_are_recorded_as_sent(): void
    {
        $this->send('contracts', $this->body('a', ['is_signed' => 0]))->assertCreated();
        $this->send('contracts', $this->body('b', ['term_type' => 'fixed', 'end_date' => now()->subDay()->toDateString()]))->assertCreated();

        $this->assertSame(['active', 'expired'], Contract::query()->orderBy('created_at')->orderBy('id')->get()->map(fn (Contract $c): string => $c->status->value)->all());
        $this->assertFalse(Contract::query()->where('status', 'active')->sole()->is_signed);
    }

    public function test_validation_errors(): void
    {
        $this->send('contracts', $this->body('x', ['category' => 'employment']))->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->send('contracts', $this->body('x', ['file_uuid' => fake()->uuid()]))->assertUnprocessable()->assertJsonValidationErrors('file_uuid');
        $this->send('contracts', $this->body('x', ['file' => $this->contractFixture('page.html')]))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->send('contracts', $this->body('x', ['is_signed' => 0, 'signed_on' => '2025-03-02']))->assertUnprocessable()->assertJsonValidationErrors('signed_on');
        $this->send('contracts', $this->body('x', ['contractable_id' => fake()->uuid()]))->assertUnprocessable()->assertJsonValidationErrors('contractable_id');
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_attachment_twice_is_one_media(): void
    {
        $contractId = $this->send('contracts', $this->body())->json('contract_id');
        $data = fn (): array => ['company_id' => $this->alpha->id, 'contract_id' => $contractId, 'title' => 'Dodatok', 'file' => $this->contractPdf('dodatok.pdf', 'amend')];

        $first = $this->send('contracts/attachments', $data())->assertCreated();
        $second = $this->send('contracts/attachments', $data())->assertOk();

        $this->assertTrue($first->json('created'));
        $this->assertFalse($second->json('created'));
        $this->assertSame($first->json('attachment_id'), $second->json('attachment_id'));
        $this->assertCount(1, Contract::query()->sole()->document?->getMedia('attachments') ?? []);
    }

    public function test_attachment_needs_a_contract_of_the_company(): void
    {
        $this->send('contracts/attachments', ['company_id' => $this->alpha->id, 'file' => $this->contractPdf()])->assertUnprocessable()->assertJsonValidationErrors('contract_id');
        $this->send('contracts/attachments', ['company_id' => $this->alpha->id, 'contract_id' => fake()->uuid(), 'file' => $this->contractPdf()])->assertNotFound();
    }

    public function test_every_route_answers_404_while_disabled(): void
    {
        config()->set('token_uploads.enabled', false);

        $this->send('contracts', $this->body())->assertNotFound();
        $this->send('contracts/attachments', ['company_id' => $this->alpha->id, 'contract_id' => fake()->uuid(), 'file' => $this->contractPdf()])->assertNotFound();
    }

    public function test_a_foreign_company_is_refused_and_audited_as_denied(): void
    {
        $outsider = Tenant::factory()->create();

        $this->send('contracts', $this->body('x', ['company_id' => $outsider->id]))->assertForbidden();

        $row = Activity::query()->withoutGlobalScopes()->where('log_name', 'ai')->sole();
        $this->assertSame('tool.upload.contract.failed', $row->description);
        $this->assertSame('denied', data_get($row->properties, 'outcome'));
        $this->assertSame(0, Contract::query()->withoutGlobalScopes()->count());
    }

    public function test_a_user_without_create_contracts_is_refused(): void
    {
        $accountant = $this->member($this->alpha, 'Účtovníčka');
        $token = $accountant->createToken('claude', ['mcp'])->plainTextToken;

        $this->send('contracts', $this->body(), $token)->assertForbidden();
        $this->assertSame(0, Contract::query()->withoutGlobalScopes()->count());
    }

    public function test_a_mobile_token_is_refused(): void
    {
        $mobile = $this->admin->createToken('phone', ['mobile'])->plainTextToken;

        $this->send('contracts', $this->body(), $mobile)->assertForbidden();
    }

    public function test_the_prompt_guard_lock_blocks_writes(): void
    {
        app(PromptGuard::class)->restrictMcp($this->admin);

        $this->send('contracts', $this->body())->assertStatus(423);
        $this->assertSame(0, Contract::query()->withoutGlobalScopes()->count());
    }

    public function test_the_audit_row_holds_file_stats_and_no_title_or_notes(): void
    {
        $this->send('contracts', $this->body())->assertCreated();

        $row = Activity::query()->withoutGlobalScopes()->where('log_name', 'ai')->sole();
        $this->assertSame('tool.upload.contract', $row->description);
        $this->assertSame(1, data_get($row->properties, 'arguments.files_count'));
        $this->assertSame('service_agreement', data_get($row->properties, 'arguments.category'));
        $dump = (string) json_encode($row->properties);
        $this->assertStringNotContainsString('Zmluva z mailu', $dump);
        $this->assertStringNotContainsString('Dátum z mailu', $dump);
    }

    public function test_a_long_contract_title_is_accepted_but_an_attachment_title_is_capped_at_120(): void
    {
        $created = $this->send('contracts', $this->body('long', ['title' => str_repeat('Z', 200)]))->assertCreated();

        $this->assertSame(200, mb_strlen((string) Contract::query()->sole()->title));

        $attachment = fn (int $length): array => [
            'company_id' => $this->alpha->id,
            'contract_id' => $created->json('contract_id'),
            'title' => str_repeat('T', $length),
            'file' => $this->contractPdf('d.pdf', 'att'.$length),
        ];

        $this->send('contracts/attachments', $attachment(121))->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->send('contracts/attachments', $attachment(120))->assertCreated();
    }

    public function test_signed_on_without_is_signed_means_signed(): void
    {
        $body = $this->body('s', ['signed_on' => '2025-03-02']);

        $this->send('contracts', $body)->assertCreated();

        $contract = Contract::query()->sole();
        $this->assertTrue($contract->is_signed);
        $this->assertSame('2025-03-02', $contract->signed_at?->toDateString());
    }

    public function test_signed_on_with_is_signed_zero_is_refused_and_string_booleans_are_not_accepted(): void
    {
        $this->send('contracts', $this->body('a', ['is_signed' => 0, 'signed_on' => '2025-03-02']))->assertUnprocessable()->assertJsonValidationErrors('signed_on');
        $this->send('contracts', $this->body('b', ['is_signed' => 'false']))->assertUnprocessable()->assertJsonValidationErrors('is_signed');
        $this->send('contracts', $this->body('c', ['is_signed' => 'true']))->assertUnprocessable()->assertJsonValidationErrors('is_signed');
        $this->assertSame(0, Contract::query()->count());
    }
}
