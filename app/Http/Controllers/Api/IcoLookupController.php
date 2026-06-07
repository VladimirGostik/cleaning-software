<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Tenants\IcoLookupData;
use App\Http\Controllers\Controller;
use App\Services\IcoLookupService;

final class IcoLookupController extends Controller
{
    public function __invoke(string $ico, IcoLookupService $service): IcoLookupData
    {
        $result = $service->lookup($ico);

        if ($result === null) {
            abort(404);
        }

        return $result;
    }
}
