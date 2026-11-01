<?php

declare(strict_types=1);

namespace Tests\Feature\Expenses;

use App\Enums\ExpenseSourceEnum;
use App\Models\Expense;
use App\Models\TemporaryUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesExpenseFixtures;
use Tests\TestCase;

final class ExpenseReceiptTest extends TestCase
{
    use CreatesExpenseFixtures;
    use RefreshDatabase;

    private const array JSON = ['Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        $this->setUpExpenses();
    }

    private function pdf(string $name = 'receipt.pdf', int $kb = 1): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".str_repeat('A', $kb * 1024));
    }

    private function disguisedExecutable(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'exe');
        file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF");

        return new UploadedFile($path, 'malware.pdf', 'application/pdf', null, true);
    }

    private function uploader(): User
    {
        $admin = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Admin', $admin);

        return $admin;
    }

    private function stage(UploadedFile $file): string
    {
        /** @var string $uuid */
        $uuid = $this->postJson('/uploads', ['file' => $file])->json('uuid');

        return $uuid;
    }

    public function test_staged_receipt_is_moved_to_the_private_documents_disk(): void
    {
        $this->webAs($this->uploader(), $this->alpha);
        $uuid = $this->stage($this->pdf());

        $this->post('/expenses', $this->expensePayload(['receipt_uuid' => $uuid]))->assertSessionHasNoErrors();

        $expense = Expense::query()->sole();
        $media = $expense->getFirstMedia(Expense::RECEIPT_COLLECTION);

        $this->assertNotNull($media);
        $this->assertSame(config('expenses.receipt.disk'), $media->disk);
        $this->assertSame($this->alpha->id, $media->getAttribute('tenant_id'));
        Storage::disk($media->disk)->assertExists($media->getPathRelativeToRoot());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(ExpenseSourceEnum::Receipt, $expense->source);
        $this->assertDatabaseCount('temporary_uploads', 0);
    }

    public function test_an_executable_renamed_pdf_is_refused(): void
    {
        $this->webAs($this->uploader(), $this->alpha);
        $uuid = $this->stage($this->disguisedExecutable());

        $this->postJson('/expenses', $this->expensePayload(['receipt_uuid' => $uuid]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt_uuid');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_an_oversized_staged_receipt_is_refused(): void
    {
        $this->webAs($this->uploader(), $this->alpha);
        $uuid = $this->stage($this->pdf('big.pdf', 3));
        config(['expenses.receipt.max_size_kb' => 2]);

        $this->postJson('/expenses', $this->expensePayload(['receipt_uuid' => $uuid]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt_uuid');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_an_eleven_megabyte_file_cannot_even_be_staged(): void
    {
        $this->webAs($this->uploader(), $this->alpha);

        $this->postJson('/uploads', ['file' => UploadedFile::fake()->create('big.pdf', 11 * 1024, 'application/pdf')])
            ->assertUnprocessable();

        $this->assertSame(0, TemporaryUpload::query()->count());
    }

    public function test_a_receipt_cannot_be_attached_to_an_expense_of_another_firm(): void
    {
        $this->webAs($this->uploader(), $this->alpha);
        $uuid = $this->stage($this->pdf());

        $this->postJson('/expenses', $this->expensePayload(['tenant_id' => $this->beta->id, 'receipt_uuid' => $uuid]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt_uuid');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_an_upload_staged_in_another_firm_is_not_usable(): void
    {
        $this->webAs($this->uploader(), $this->beta);
        $uuid = $this->stage($this->pdf());

        $this->webAs($this->uploader(), $this->alpha)
            ->postJson('/expenses', $this->expensePayload(['receipt_uuid' => $uuid]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt_uuid');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_receipt_is_streamed_inline_to_authorised_users_only(): void
    {
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();
        $this->actingAsTenantUser('Admin', $this->alpha, $this->accountant);
        $expense->addMedia($this->pdf('invoice.pdf'))->toMediaCollection(Expense::RECEIPT_COLLECTION);

        $response = $this->webAs($this->accountant, $this->alpha)->get("/expenses/{$expense->id}/receipt");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-1.4', $response->streamedContent());

        $secretary = $this->member($this->alpha, 'Sekretárka');
        $this->webAs($secretary, $this->alpha)->get("/expenses/{$expense->id}/receipt")->assertForbidden();

        $outsider = $this->member($this->beta, 'Účtovníčka');
        $this->webAs($outsider, $this->beta)->get("/expenses/{$expense->id}/receipt")->assertNotFound();

        $this->consoleContext();
        $this->get("/expenses/{$expense->id}/receipt")->assertRedirect();
    }

    public function test_expense_without_a_receipt_returns_404_on_the_stream_route(): void
    {
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();

        $this->webAs($this->accountant, $this->alpha)->get("/expenses/{$expense->id}/receipt")->assertNotFound();
    }

    public function test_api_attaches_replaces_and_streams_a_receipt(): void
    {
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();

        $this->apiAs($this->accountant, $this->alpha)
            ->post("/api/expenses/{$expense->id}/receipt", ['receipt' => $this->pdf('first.pdf')], self::JSON)
            ->assertOk()
            ->assertJsonPath('has_receipt', true)
            ->assertJsonPath('receipt.file_name', 'first.pdf')
            ->assertJsonPath('receipt.download_url', route('api.expenses.receipt', $expense));

        $this->apiAs($this->accountant, $this->alpha)
            ->post("/api/expenses/{$expense->id}/receipt", ['receipt' => $this->pdf('second.pdf')], self::JSON)
            ->assertOk();

        $media = $expense->refresh()->getMedia(Expense::RECEIPT_COLLECTION);
        $this->assertCount(1, $media);
        $stored = $media->first();
        $this->assertNotNull($stored);
        $this->assertSame('second.pdf', $stored->file_name);
        $this->assertSame($this->alpha->id, $stored->getAttribute('tenant_id'));

        $this->apiAs($this->accountant, $this->alpha)
            ->get("/api/expenses/{$expense->id}/receipt")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_api_receipt_upload_is_validated_and_authorised(): void
    {
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();
        $url = "/api/expenses/{$expense->id}/receipt";

        $this->apiAs($this->accountant, $this->alpha)->post($url, ['receipt' => $this->disguisedExecutable()], self::JSON)
            ->assertUnprocessable()->assertJsonValidationErrors('receipt');
        $this->apiAs($this->accountant, $this->alpha)->post($url, ['receipt' => UploadedFile::fake()->create('big.pdf', 11 * 1024, 'application/pdf')], self::JSON)
            ->assertUnprocessable()->assertJsonValidationErrors('receipt');
        $this->apiAs($this->accountant, $this->alpha)->post($url, [], self::JSON)
            ->assertUnprocessable()->assertJsonValidationErrors('receipt');

        $secretary = $this->member($this->alpha, 'Sekretárka');
        $this->apiAs($secretary, $this->alpha)->post($url, ['receipt' => $this->pdf()], self::JSON)->assertForbidden();

        $this->apiAs($this->accountant, $this->beta)->post($url, ['receipt' => $this->pdf()], self::JSON)->assertNotFound();

        $this->assertCount(0, $expense->refresh()->getMedia(Expense::RECEIPT_COLLECTION));
    }

    public function test_web_attach_endpoint_serves_roles_that_cannot_stage_uploads(): void
    {
        $expense = Expense::factory()->forTenant($this->alpha)->createdBy($this->accountant)->create();

        $this->webAs($this->accountant, $this->alpha)->postJson('/uploads', ['file' => $this->pdf()])->assertForbidden();

        $this->webAs($this->accountant, $this->alpha)
            ->post("/expenses/{$expense->id}/receipt", ['receipt' => $this->pdf('direct.pdf')])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('direct.pdf', $expense->refresh()->getFirstMedia(Expense::RECEIPT_COLLECTION)?->file_name);

        $this->webAs($this->accountant, $this->alpha)
            ->postJson("/expenses/{$expense->id}/receipt", ['receipt' => $this->disguisedExecutable()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt');

        $secretary = $this->member($this->alpha, 'Sekretárka');
        $this->webAs($secretary, $this->alpha)->post("/expenses/{$expense->id}/receipt", ['receipt' => $this->pdf()])->assertForbidden();
    }
}
