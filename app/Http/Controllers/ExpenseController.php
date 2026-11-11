<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Expenses\ExpenseAnalyzeData;
use App\Data\Expenses\ExpenseBatchStoreData;
use App\Data\Expenses\ExpenseConfirmData;
use App\Data\Expenses\ExpenseImportData;
use App\Data\Expenses\ExpenseImportResultData;
use App\Data\Expenses\ExpenseImportUploadData;
use App\Data\Expenses\ExpenseReceiptUploadData;
use App\Data\Expenses\ExpenseUpsertData;
use App\Enums\NavIconEnum;
use App\Enums\PermissionEnum;
use App\Models\Expense;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\ExpenseImportService;
use App\Services\Expenses\EmployeeExpenseService;
use App\Services\ExpenseService;
use App\Support\MediaFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly ExpenseImportService $imports,
        private readonly EmployeeExpenseService $employeeExpenses,
    ) {}

    #[Authorize('viewAny', Expense::class)]
    #[NavItem(label: 'app.expenses', route: 'expenses.index', icon: NavIconEnum::Expenses, permission: PermissionEnum::ViewExpenses->value, order: 36)]
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Expenses/Index', [
            'expenses' => $this->expenses->paginate($request),
            'totals' => $this->expenses->totals($request),
            'filters' => $request->query(),
            'context' => $this->expenses->formContext($actor),
        ]);
    }

    #[Authorize('import', Expense::class)]
    public function importPage(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $flashed = $request->session()->get('result');

        return Inertia::render('Expenses/Import', [
            'context' => $this->expenses->formContext($actor),
            'result' => is_array($flashed) ? ExpenseImportResultData::from($flashed) : null,
        ]);
    }

    #[Authorize('create', Expense::class)]
    public function analyze(ExpenseAnalyzeData $data, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->expenses->analyze($data, $actor));
    }

    #[Authorize('import', Expense::class)]
    public function importPreview(ExpenseImportUploadData $data, Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json($this->imports->preview($data->file, $actor));
    }

    #[Authorize('import', Expense::class)]
    public function import(ExpenseImportData $data, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return to_route('expenses.import')->with('result', $this->imports->import($data, $actor)->toArray());
    }

    #[Authorize('create', Expense::class)]
    public function store(ExpenseUpsertData $data, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->expenses->assertMayCreate($data, $actor);

        $expense = $this->expenses->create($data, $actor, $request->session()->getId());

        $visibleHere = $expense->tenant_id === current_tenant_id();

        return to_route($visibleHere ? 'expenses.show' : 'expenses.index', $visibleHere ? $expense : [])
            ->with('success', __('app.expense_created'));
    }

    #[Authorize('create', Expense::class)]
    public function batchStore(ExpenseBatchStoreData $data, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $created = $this->employeeExpenses->createBatch($data, $actor, $request->session()->getId());

        return to_route('expenses.index')->with('success', __('app.expense_batch_created', ['count' => count($created)]));
    }

    #[Authorize('view', 'expense')]
    public function show(Expense $expense, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Expenses/Show', [
            'expense' => $this->expenses->detail($expense, $actor),
            'context' => $this->expenses->formContext($actor),
        ]);
    }

    #[Authorize('update', 'expense')]
    public function update(ExpenseUpsertData $data, Expense $expense, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->expenses->update($expense, $data, $actor, $request->session()->getId());

        return to_route('expenses.show', $expense)->with('success', __('app.expense_updated'));
    }

    #[Authorize('delete', 'expense')]
    public function destroy(Expense $expense): RedirectResponse
    {
        $this->expenses->delete($expense);

        return to_route('expenses.index')->with('success', __('app.expense_deleted'));
    }

    #[Authorize('confirm', 'expense')]
    public function confirm(ExpenseConfirmData $data, Expense $expense): RedirectResponse
    {
        $this->expenses->confirm($expense, $data);

        return back()->with('success', __('app.expense_confirmed'));
    }

    #[Authorize('update', 'expense')]
    public function attachReceipt(ExpenseReceiptUploadData $data, Expense $expense): RedirectResponse
    {
        $this->expenses->attachReceipt($expense, $data->receipt);

        return back()->with('success', __('app.expense_receipt_attached'));
    }

    #[Authorize('view', 'expense')]
    public function receipt(Expense $expense): StreamedResponse
    {
        $media = $expense->getFirstMedia(Expense::RECEIPT_COLLECTION);

        abort_if($media === null, 404);

        return response()->stream(
            static function () use ($media): void {
                /** @var resource $stream */
                $stream = $media->stream();
                fpassthru($stream);
            },
            200,
            [
                'Content-Type' => (string) $media->mime_type,
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, MediaFiles::cleanName($media->name.'.'.$media->extension), 'receipt'),
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
