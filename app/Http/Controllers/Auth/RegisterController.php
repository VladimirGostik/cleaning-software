<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Data\Auth\RegisterData;
use App\Enums\SupportedLanguage;
use App\Enums\TenantColorEnum;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class RegisterController extends Controller
{
    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register', [
            'invitableRoles' => ['Vedúca', 'Upratovačka', 'Sekretárka', 'Účtovníčka', 'Zákazník'],
            'colorOptions' => TenantColorEnum::options(),
            'languages' => SupportedLanguage::options(),
        ]);
    }

    public function register(RegisterData $data, RegistrationService $service): RedirectResponse
    {
        $user = $service->register($data);

        Auth::login($user);

        /** @var Tenant|null $tenant */
        $tenant = $user->tenants()->first();

        session(['active_tenant_id' => $tenant?->id]);
        session()->regenerate();

        return to_route('dashboard')
            ->with('flash.success', __('app.register.success'))
            ->with('justRegistered', true);
    }
}
