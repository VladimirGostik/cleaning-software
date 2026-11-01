<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Employees\EmployeeAccessData;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\EmployeeAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class EmployeeAccessController extends Controller
{
    public function __construct(private readonly EmployeeAccessService $access) {}

    #[Authorize('grantAccess', 'employee')]
    public function store(EmployeeAccessData $data, TenantMembership $employee, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $granted = $this->access->grant($employee, $data, $actor);

        $message = $granted->isInvitationPending()
            ? __('app.employee_access_invited')
            : __('app.employee_access_granted');

        return to_route('employees.show', $employee)->with('success', $message);
    }

    #[Authorize('revokeAccess', 'employee')]
    public function destroy(TenantMembership $employee, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->access->revoke($employee, $actor);

        return to_route('employees.show', $employee)->with('success', __('app.employee_access_revoked'));
    }

    #[Authorize('resendInvitation', 'employee')]
    public function invitation(TenantMembership $employee, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->access->resendInvitation($employee, $actor);

        return to_route('employees.show', $employee)->with('success', __('app.employee_access_invitation_resent'));
    }
}
