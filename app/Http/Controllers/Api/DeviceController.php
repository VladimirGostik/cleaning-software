<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Devices\DeviceRegisterData;
use App\Data\Devices\DeviceUnregisterData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeviceTokenService;
use App\Services\MobileSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

/** Device tokens belong to the caller (device = person); every query is scoped to the authenticated user. */
#[Group('Devices', 'Push notification device registry')]
#[Authenticated]
final class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceTokenService $devices,
        private readonly MobileSessionService $sessions,
    ) {}

    #[Endpoint('Register device', 'Upserts an FCM registration token for the caller; a token seen on another account is re-homed.')]
    #[Response(null, 204, 'Registered')]
    public function store(Request $request, DeviceRegisterData $data): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->devices->register($user, $data, $this->sessions->currentFor($user)?->id);

        return response()->json(null, 204);
    }

    #[Endpoint('Unregister device', 'Deletes one of the caller\'s own tokens (call on logout); the token travels in the JSON body, not the URL. Unknown tokens are a no-op.')]
    #[Response(null, 204, 'Unregistered')]
    public function destroy(Request $request, DeviceUnregisterData $data): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->devices->unregister($user, $data->token);

        return response()->json(null, 204);
    }
}
