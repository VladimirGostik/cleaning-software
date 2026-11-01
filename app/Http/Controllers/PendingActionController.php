<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NavIconEnum;
use App\Data\Ai\PendingActionData;
use App\Enums\ConfirmationChannelEnum;
use App\Models\PendingAction;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\Ai\ActionConfirmationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class PendingActionController extends Controller
{
    public function __construct(private readonly ActionConfirmationService $actions) {}

    #[Authorize('viewAny', PendingAction::class)]
    #[NavItem(label: 'app.pending_actions', route: 'pending-actions.index', icon: NavIconEnum::PendingActions, order: 46)]
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('PendingActions/Index', [
            'pendingActions' => PendingActionData::collect($this->actions->pendingFor($actor)),
        ]);
    }

    #[Authorize('confirm', 'pendingAction')]
    public function confirm(PendingAction $pendingAction, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $result = $this->actions->execute($actor, $pendingAction, ConfirmationChannelEnum::App);

        return back()->with('success', $result->message);
    }

    #[Authorize('cancel', 'pendingAction')]
    public function cancel(PendingAction $pendingAction, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->actions->cancel($actor, $pendingAction);

        return back()->with('success', __('app.pending_action_cancelled'));
    }
}
