<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Clients\ClientOptionData;
use App\Data\Objects\ObjectDetailData;
use App\Data\Objects\ObjectUpsertData;
use App\Data\Objects\ObjectVisitRecordsData;
use App\Data\Schedule\WorkBreakdownDetailData;
use App\Enums\NavIconEnum;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkBreakdown;
use App\Navigation\NavItem;
use App\Services\ContractService;
use App\Services\ObjectService;
use App\Services\ObjectVisitItemService;
use App\Services\QuoteService;
use App\Services\WorkRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class ObjectController extends Controller
{
    public function __construct(
        private readonly ObjectService $objects,
        private readonly ContractService $contracts,
        private readonly QuoteService $quotes,
        private readonly ObjectVisitItemService $visitItems,
        private readonly WorkRecordService $workRecords,
    ) {}

    #[Authorize('viewAny', CleaningObject::class)]
    #[NavItem(label: 'app.objects', route: 'objects.index', icon: NavIconEnum::Buildings, permission: PermissionEnum::ViewObjects->value, order: 31)]
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Objects/Index', [
            'objects' => $this->objects->paginate($request, $actor),
            'filters' => $request->query(),
            'filterOptions' => [
                'clients' => $this->clientOptions($actor),
            ],
        ]);
    }

    #[Authorize('view', 'object')]
    public function show(CleaningObject $object, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $includeContacts = $actor->can('viewContacts', CleaningObject::class);
        $includeBilling = $actor->can('viewBilling', CleaningObject::class);

        $object->load(array_filter([
            'client',
            $includeContacts ? 'contacts' : null,
            'workBreakdowns.tasks',
            'workBreakdowns.contract:id,title,status',
        ]));

        return Inertia::render('Objects/Show', [
            'object' => ObjectDetailData::fromModel($object, $includeContacts, $includeBilling),
            'clients' => $actor->can('update', $object) ? $this->clientOptions($actor) : [],
            'workBreakdowns' => $object->workBreakdowns
                ->map(fn (WorkBreakdown $breakdown) => WorkBreakdownDetailData::fromModel($breakdown))
                ->all(),
            'contracts' => $this->contracts->listFor($object, $actor),
            'canCreateContract' => $this->contracts->canCreateFor($object, $actor),
            'quotes' => $this->quotes->listFor($object, $actor),
            'canCreateQuote' => $actor->can('create', Quote::class),
            'visitItems' => $actor->can('viewVisitItems', CleaningObject::class) ? $this->visitItems->listFor($object) : null,
            'visitRecords' => fn (): ?ObjectVisitRecordsData => $actor->can('viewVisitRecords', CleaningObject::class)
                ? $this->workRecords->overview($object, $this->recordsMonth($request), $actor)
                : null,
        ]);
    }

    #[Authorize('create', CleaningObject::class)]
    public function store(ObjectUpsertData $data, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $object = $this->objects->create($data, $actor);

        return to_route('objects.show', $object)->with('success', __('app.object_created'));
    }

    #[Authorize('update', 'object')]
    public function update(ObjectUpsertData $data, CleaningObject $object, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->objects->update($object, $data, $actor);

        return to_route('objects.show', $object)->with('success', __('app.object_updated'));
    }

    #[Authorize('delete', 'object')]
    public function deactivate(CleaningObject $object): RedirectResponse
    {
        $this->objects->deactivate($object);

        return to_route('objects.show', $object)->with('success', __('app.object_deactivated'));
    }

    #[Authorize('update', 'object')]
    public function reactivate(CleaningObject $object): RedirectResponse
    {
        $this->objects->reactivate($object);

        return to_route('objects.show', $object)->with('success', __('app.object_reactivated'));
    }

    private function recordsMonth(Request $request): string
    {
        $month = $request->query('records_month');

        if (is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            return $month;
        }

        return now(config()->string('scheduling.timezone'))->format('Y-m');
    }

    /** @return array<int, ClientOptionData> */
    private function clientOptions(User $actor): array
    {
        if (! $actor->can(PermissionEnum::ViewAllObjects->value)) {
            return [];
        }

        return Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => ClientOptionData::fromModel($client))
            ->all();
    }
}
