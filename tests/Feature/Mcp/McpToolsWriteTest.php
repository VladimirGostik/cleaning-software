<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Contracts\RendersInvoicePdf;
use App\Contracts\RendersQuotePdf;
use App\Enums\ExpenseSourceEnum;
use App\Enums\ExpenseStatusEnum;
use App\Enums\TaskSourceEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\CreatesMcpFixtures;
use Tests\TestCase;

final class McpToolsWriteTest extends TestCase
{
    use CreatesMcpFixtures;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionCatalog();
        $this->alpha = $this->firm('Alpha');
        $this->beta = $this->firm('Beta');
        $this->admin = $this->member($this->alpha, 'Admin');
        $this->member($this->beta, 'Admin', $this->admin);
        $this->token = $this->mcpToken($this->admin);
    }

    public function test_create_task_creates_an_mcp_sourced_task_in_the_company(): void
    {
        $result = $this->callTool($this->token, 'create_task', [
            'company_id' => $this->alpha->id,
            'title' => 'Call the client',
            'priority' => 'high',
        ]);

        $this->assertFalse($result->isError, $result->text);
        $task = Task::unscopedForSystem()->findOrFail($result->string('id'));
        $this->assertSame($this->alpha->id, $task->tenant_id);
        $this->assertSame($this->admin->id, $task->created_by_user_id);
        $this->assertSame(TaskSourceEnum::Mcp, $task->source);
        $audit = $this->aiActivities()->sole();
        $this->assertSame('tool.create_task', $audit->description);
        $this->assertSame($this->alpha->id, $audit->tenant_id);
    }

    public function test_create_task_rejects_an_assignee_from_another_firm(): void
    {
        $outsider = $this->member($this->beta, 'Sekretárka');
        $membership = $this->membershipOf($outsider, $this->beta);

        $result = $this->callTool($this->token, 'create_task', [
            'company_id' => $this->alpha->id,
            'title' => 'Cross-firm assignment',
            'assigned_membership_id' => $membership->id,
        ]);

        $this->assertTrue($result->isError);
        $this->assertSame(0, Task::unscopedForSystem()->count());
    }

    public function test_create_task_needs_the_create_permission(): void
    {
        $cleaner = $this->member($this->alpha, 'Interná upratovačka');

        $result = $this->callTool($this->mcpToken($cleaner), 'create_task', ['company_id' => $this->alpha->id, 'title' => 'Nope']);

        $this->assertTrue($result->isError);
        $this->assertSame(0, Task::unscopedForSystem()->count());
    }

    public function test_task_update_complete_and_assign(): void
    {
        $colleague = $this->member($this->alpha, 'Sekretárka');
        $task = Task::factory()->forTenant($this->alpha)->createdBy($this->admin)->create(['title' => 'Old']);

        $updated = $this->callTool($this->token, 'update_task', ['company_id' => $this->alpha->id, 'task_id' => $task->id, 'title' => 'New', 'priority' => 'low']);
        $this->assertFalse($updated->isError, $updated->text);
        $this->assertSame('New', $task->refresh()->title);

        $assigned = $this->callTool($this->token, 'assign_task', ['company_id' => $this->alpha->id, 'task_id' => $task->id, 'assigned_membership_id' => $this->membershipOf($colleague, $this->alpha)->id]);
        $this->assertFalse($assigned->isError, $assigned->text);
        $this->assertSame($this->membershipOf($colleague, $this->alpha)->id, $task->refresh()->assigned_membership_id);

        $done = $this->callTool($this->token, 'complete_task', ['company_id' => $this->alpha->id, 'task_id' => $task->id]);
        $this->assertFalse($done->isError, $done->text);
        $this->assertSame(TaskStatusEnum::Done, $task->refresh()->status);

        $again = $this->callTool($this->token, 'complete_task', ['company_id' => $this->alpha->id, 'task_id' => $task->id]);
        $this->assertTrue($again->isError);
    }

    public function test_another_users_global_task_cannot_be_touched(): void
    {
        $stranger = User::factory()->create();
        $global = Task::factory()->global()->createdBy($stranger)->create();

        foreach (['complete_task', 'update_task'] as $tool) {
            $this->assertTrue($this->callTool($this->token, $tool, ['company_id' => $this->alpha->id, 'task_id' => $global->id, 'title' => 'x'])->isError, $tool);
        }

        $this->assertSame(TaskStatusEnum::Open, $global->refresh()->status);
    }

    public function test_create_expense_without_category_is_a_draft_and_with_category_confirmed(): void
    {
        $draft = $this->callTool($this->token, 'create_expense', ['company_id' => $this->alpha->id, 'date' => '2026-10-30', 'amount' => '42.50', 'supplier_name' => 'Shell']);
        $this->assertFalse($draft->isError, $draft->text);

        $confirmed = $this->callTool($this->token, 'create_expense', ['company_id' => $this->alpha->id, 'date' => '2026-10-31', 'amount' => '10.00', 'category' => 'fuel']);
        $this->assertFalse($confirmed->isError, $confirmed->text);

        $this->assertSame(ExpenseStatusEnum::Draft, Expense::findOrFail($draft->string('id'))->status);
        $this->assertSame(ExpenseStatusEnum::Confirmed, Expense::findOrFail($confirmed->string('id'))->status);
        $this->assertSame(ExpenseSourceEnum::Mcp, Expense::findOrFail($draft->string('id'))->source);
        $this->assertSame($this->alpha->id, Expense::findOrFail($draft->string('id'))->tenant_id);
    }

    public function test_create_expense_requires_manage_expenses_and_valid_amounts(): void
    {
        $secretary = $this->member($this->alpha, 'Sekretárka');

        $denied = $this->callTool($this->mcpToken($secretary), 'create_expense', ['company_id' => $this->alpha->id, 'date' => '2026-10-30', 'amount' => '5.00']);
        $this->assertTrue($denied->isError);

        $invalid = $this->callTool($this->token, 'create_expense', ['company_id' => $this->alpha->id, 'date' => '2026-10-30', 'amount' => '-3']);
        $this->assertTrue($invalid->isError);

        $this->assertSame(0, Expense::query()->withoutGlobalScopes()->count());
    }

    public function test_update_expense_keeps_omitted_fields_and_confirms_with_a_category(): void
    {
        $created = $this->callTool($this->token, 'create_expense', ['company_id' => $this->alpha->id, 'date' => '2026-10-30', 'amount' => '42.50', 'supplier_name' => 'Shell']);
        $id = $created->string('id');

        $result = $this->callTool($this->token, 'update_expense', ['company_id' => $this->alpha->id, 'expense_id' => $id, 'category' => 'fuel']);

        $this->assertFalse($result->isError, $result->text);
        $expense = Expense::findOrFail($id);
        $this->assertSame(ExpenseStatusEnum::Confirmed, $expense->status);
        $this->assertSame('Shell', $expense->supplier_name);
        $this->assertSame('42.50', $expense->amount);
    }

    public function test_quote_draft_create_and_update_replace_items_and_keep_the_number(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->alpha->id]);

        $created = $this->callTool($this->token, 'create_quote_draft', [
            'company_id' => $this->alpha->id,
            'client_id' => $client->id,
            'issue_date' => '2026-11-02',
            'valid_until' => '2026-12-02',
            'items' => [['description' => 'Office cleaning', 'quantity' => 2, 'unit_price' => 50]],
        ]);
        $this->assertFalse($created->isError, $created->text);
        $quote = Quote::findOrFail($created->string('id'));
        $this->assertSame('draft', $quote->status->value);
        $this->assertSame('100.00', $quote->total);

        $updated = $this->callTool($this->token, 'update_quote_draft', [
            'company_id' => $this->alpha->id,
            'quote_id' => $quote->id,
            'subject' => 'Spring cleaning',
            'items' => [['description' => 'Windows', 'quantity' => 1, 'unit_price' => 30]],
        ]);
        $this->assertFalse($updated->isError, $updated->text);
        $quote->refresh();
        $this->assertSame('Spring cleaning', $quote->subject);
        $this->assertSame('30.00', $quote->total);
        $this->assertSame($client->id, $quote->client_id);
        $this->assertCount(1, $quote->items);
    }

    public function test_a_sent_quote_is_not_editable_through_the_tool(): void
    {
        $quote = Quote::factory()->sent()->create(['tenant_id' => $this->alpha->id]);

        $result = $this->callTool($this->token, 'update_quote_draft', ['company_id' => $this->alpha->id, 'quote_id' => $quote->id, 'subject' => 'Changed']);

        $this->assertTrue($result->isError);
        $this->assertNotSame('Changed', $quote->refresh()->subject);
    }

    public function test_invoice_draft_create_and_update_keep_unmentioned_settings(): void
    {
        $created = $this->callTool($this->token, 'create_invoice_draft', [
            'company_id' => $this->alpha->id,
            'type' => 'one_off',
            'issue_date' => '2026-11-02',
            'delivery_date' => '2026-11-02',
            'due_date' => '2026-11-16',
            'customer_name' => 'Acme s.r.o.',
            'customer_email' => 'acme@example.com',
            'items' => [['description' => 'Cleaning', 'quantity' => 1, 'unit_price' => 80]],
        ]);
        $this->assertFalse($created->isError, $created->text);
        $invoice = Invoice::findOrFail($created->string('id'));
        $this->assertSame('draft', $invoice->status->value);
        $this->assertNull($invoice->number);
        $invoice->update(['deposit' => '5.00']);

        $updated = $this->callTool($this->token, 'update_invoice_draft', [
            'company_id' => $this->alpha->id,
            'invoice_id' => $invoice->id,
            'note' => 'Thanks',
        ]);
        $this->assertFalse($updated->isError, $updated->text);
        $invoice->refresh();
        $this->assertSame('Thanks', $invoice->note);
        $this->assertSame('Acme s.r.o.', $invoice->customer_name);
        $this->assertSame('5.00', $invoice->deposit);
        $this->assertCount(1, $invoice->items);
    }

    public function test_write_tools_never_cross_the_company_boundary(): void
    {
        $foreignQuote = Quote::factory()->create(['tenant_id' => $this->beta->id]);
        $foreignInvoice = Invoice::factory()->create(['tenant_id' => $this->beta->id]);

        $this->assertTrue($this->callTool($this->token, 'update_quote_draft', ['company_id' => $this->alpha->id, 'quote_id' => $foreignQuote->id, 'note' => 'x'])->isError);
        $this->assertTrue($this->callTool($this->token, 'update_invoice_draft', ['company_id' => $this->alpha->id, 'invoice_id' => $foreignInvoice->id, 'note' => 'x'])->isError);
        $this->assertNull($foreignInvoice->refresh()->note);
    }

    public function test_pdf_resources_render_for_authorised_users_and_refuse_foreign_companies(): void
    {
        $this->app->instance(RendersInvoicePdf::class, new class implements RendersInvoicePdf
        {
            public function render(Invoice $invoice): string
            {
                return '%PDF-invoice';
            }
        });
        $this->app->instance(RendersQuotePdf::class, new class implements RendersQuotePdf
        {
            public function render(Quote $quote): string
            {
                return '%PDF-quote';
            }
        });

        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id]);
        $quote = Quote::factory()->create(['tenant_id' => $this->alpha->id]);

        $response = $this->rpc($this->token, 'resources/read', ['uri' => "invoice-pdf://{$this->alpha->id}/{$invoice->id}"])->assertOk();
        $this->assertSame(base64_encode('%PDF-invoice'), $response->json('result.contents.0.blob'));

        $response = $this->rpc($this->token, 'resources/read', ['uri' => "quote-pdf://{$this->alpha->id}/{$quote->id}"])->assertOk();
        $this->assertSame(base64_encode('%PDF-quote'), $response->json('result.contents.0.blob'));

        $wrongCompany = $this->rpc($this->token, 'resources/read', ['uri' => "invoice-pdf://{$this->beta->id}/{$invoice->id}"])->assertOk();
        $this->assertNull($wrongCompany->json('result.contents.0.blob'));

        $stranger = $this->member($this->firm('Delta'), 'Admin');
        $denied = $this->rpc($this->mcpToken($stranger), 'resources/read', ['uri' => "invoice-pdf://{$this->alpha->id}/{$invoice->id}"])->assertOk();
        $this->assertNull($denied->json('result.contents.0.blob'));
    }

    public function test_a_failing_pdf_renderer_is_a_resource_error_and_audited(): void
    {
        $this->app->instance(RendersInvoicePdf::class, new class implements RendersInvoicePdf
        {
            public function render(Invoice $invoice): string
            {
                throw new RuntimeException('chromium crashed');
            }
        });
        $invoice = Invoice::factory()->issued()->create(['tenant_id' => $this->alpha->id]);

        $response = $this->rpc($this->token, 'resources/read', ['uri' => "invoice-pdf://{$this->alpha->id}/{$invoice->id}"])->assertOk();

        $this->assertNull($response->json('result.contents.0.blob'));
        $row = $this->aiActivities()->sole();
        $this->assertSame('tool.invoice_pdf.failed', $row->description);
        $this->assertStringNotContainsString('chromium', (string) $response->getContent());
    }

    public function test_task_dates_with_an_offset_are_stored_as_utc_through_the_tools_and_the_api(): void
    {
        $created = $this->callTool($this->token, 'create_task', [
            'company_id' => $this->alpha->id,
            'title' => 'Offset',
            'due_at' => '2026-11-05T09:30:00+01:00',
            'remind_at' => '2026-11-05T08:00:00+01:00',
        ]);
        $this->assertFalse($created->isError, $created->text);
        $task = Task::unscopedForSystem()->findOrFail($created->string('id'));
        $this->assertSame('2026-11-05 08:30:00', $task->due_at?->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-05 07:00:00', $task->remind_at?->utc()->format('Y-m-d H:i:s'));

        $this->callTool($this->token, 'update_task', ['company_id' => $this->alpha->id, 'task_id' => $task->id, 'due_at' => '2026-11-06T10:00:00+02:00']);
        $this->assertSame('2026-11-06 08:00:00', $task->refresh()->due_at?->utc()->format('Y-m-d H:i:s'));

        $this->app['auth']->forgetGuards();
        $this->apiAs($this->admin, $this->alpha)->postJson('/api/tasks', ['title' => 'Api', 'priority' => 'normal', 'due_at' => '2026-11-05T09:30:00+01:00'])->assertCreated();
        $api = Task::unscopedForSystem()->where('title', 'Api')->firstOrFail();
        $this->assertSame('2026-11-05 08:30:00', $api->due_at?->utc()->format('Y-m-d H:i:s'));
    }
}
