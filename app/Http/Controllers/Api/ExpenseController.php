<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Expenses\ExpenseAnalyzeData;
use App\Data\Expenses\ExpenseConfirmData;
use App\Data\Expenses\ExpenseDetailData;
use App\Data\Expenses\ExpenseDraftData;
use App\Data\Expenses\ExpenseFormContextData;
use App\Data\Expenses\ExpenseListItemData;
use App\Data\Expenses\ExpenseReceiptUploadData;
use App\Data\Expenses\ExpenseUpsertData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ExpenseController as WebExpenseController;
use App\Models\Expense;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Expenses', 'Expense ledger, receipts, AI analysis')]
#[Authenticated]
final class ExpenseController extends Controller
{
    private const string RECEIPT_ROUTE = 'api.expenses.receipt';

    public function __construct(
        private readonly ExpenseService $expenses,
    ) {}

    #[Endpoint('List expenses', 'Expenses of all companies the user may view (scope=all) or of the active company (scope=company, default).')]
    #[QueryParam('scope', 'string', 'all or company.', required: false, example: 'all')]
    #[QueryParam('filter[status]', 'string', 'draft or confirmed.', required: false, example: 'draft')]
    #[QueryParam('filter[category]', 'string', 'Expense category.', required: false)]
    #[QueryParam('filter[source]', 'string', 'manual, receipt, bank_statement or mcp.', required: false)]
    #[QueryParam('filter[tenant_id]', 'string', 'Restrict to one company.', required: false)]
    #[QueryParam('filter[date]', 'string', 'Date (Y-m-d), optionally with an operator such as >=2026-01-01.', required: false)]
    #[QueryParam('filter[search]', 'string', 'Supplier or description contains.', required: false)]
    #[QueryParam('sort', 'string', 'date, amount, created_at, status, supplier_name; prefix - for descending.', required: false, example: '-date')]
    #[ResponseFromSpatieData(ExpenseListItemData::class, Expense::class, paginated: true)]
    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $acrossCompanies = $request->query('scope') === 'all';

        Gate::forUser($actor)->authorize($acrossCompanies ? 'viewAnyAcrossCompanies' : 'viewAny', Expense::class);

        return response()->json($acrossCompanies
            ? $this->expenses->listAcrossCompanies($request, $actor)
            : $this->expenses->paginate($request));
    }

    #[Authorize('create', Expense::class)]
    #[Endpoint('Expense form context', 'Companies where the user may manage expenses, and the category list.')]
    #[ResponseFromSpatieData(ExpenseFormContextData::class, User::class)]
    public function formContext(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->expenses->formContext($actor));
    }

    #[Authorize('create', Expense::class)]
    #[Endpoint('Analyze receipt or text', 'Multipart receipt (pdf, jpeg, png, webp, max 10 MB) or text. Returns a prefill with a proposed category and company; nothing is saved. 422 with errors.ai when the AI provider is unavailable.')]
    #[ResponseFromSpatieData(ExpenseDraftData::class, User::class)]
    public function analyze(ExpenseAnalyzeData $data, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->expenses->analyze($data, $actor));
    }

    #[Authorize('create', Expense::class)]
    #[Endpoint('Create expense', 'tenant_id defaults to the active company. A confirmed expense needs a category.')]
    #[ResponseFromSpatieData(ExpenseDetailData::class, Expense::class, status: 201)]
    public function store(ExpenseUpsertData $data, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->expenses->assertMayCreate($data, $actor);

        $expense = $this->expenses->create($data, $actor, '');

        return response()->json($this->expenses->detail($expense, $actor, self::RECEIPT_ROUTE), 201);
    }

    #[Authorize('view', 'expense')]
    #[Endpoint('Show expense', 'The expense must belong to the active company (X-Tenant-Id).')]
    #[ResponseFromSpatieData(ExpenseDetailData::class, Expense::class)]
    public function show(Expense $expense, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->expenses->detail($expense, $actor, self::RECEIPT_ROUTE));
    }

    #[Authorize('update', 'expense')]
    #[Endpoint('Update expense', 'The company never changes on update.')]
    #[ResponseFromSpatieData(ExpenseDetailData::class, Expense::class)]
    public function update(ExpenseUpsertData $data, Expense $expense, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $updated = $this->expenses->update($expense, $data, $actor, '');

        return response()->json($this->expenses->detail($updated, $actor, self::RECEIPT_ROUTE));
    }

    #[Authorize('delete', 'expense')]
    #[Endpoint('Delete expense')]
    public function destroy(Expense $expense): Response
    {
        $this->expenses->delete($expense);

        return response()->noContent();
    }

    #[Authorize('confirm', 'expense')]
    #[Endpoint('Confirm draft expense', 'Sets the category and confirms. 422 when the expense is already confirmed.')]
    #[ResponseFromSpatieData(ExpenseDetailData::class, Expense::class)]
    public function confirm(ExpenseConfirmData $data, Expense $expense, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $confirmed = $this->expenses->confirm($expense, $data);

        return response()->json($this->expenses->detail($confirmed, $actor, self::RECEIPT_ROUTE));
    }

    #[Authorize('update', 'expense')]
    #[Endpoint('Attach receipt', 'Multipart receipt (pdf, jpeg, png, webp, max 10 MB); replaces the current one.')]
    #[ResponseFromSpatieData(ExpenseDetailData::class, Expense::class)]
    public function attachReceipt(ExpenseReceiptUploadData $data, Expense $expense, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $updated = $this->expenses->attachReceipt($expense, $data->receipt);

        return response()->json($this->expenses->detail($updated, $actor, self::RECEIPT_ROUTE));
    }

    #[Authorize('view', 'expense')]
    #[Endpoint('Receipt file', 'Streams the receipt inline.')]
    public function receipt(Expense $expense, WebExpenseController $web): StreamedResponse
    {
        return $web->receipt($expense);
    }
}
