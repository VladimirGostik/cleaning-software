<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Navigation\NavItem;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    #[NavItem(label: 'app.dashboard', route: 'dashboard', icon: 'HomeIcon', order: 10)]
    public function __invoke(Request $request, DashboardService $service): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Dashboard', ['overview' => $service->overviewFor($user)]);
    }
}
