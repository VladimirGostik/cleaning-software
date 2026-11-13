<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Objects\ObjectVisitItemsSyncData;
use App\Models\CleaningObject;
use App\Services\ObjectVisitItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

final class ObjectVisitItemController extends Controller
{
    public function __construct(private readonly ObjectVisitItemService $items) {}

    #[Authorize('manageVisitItems', 'object')]
    public function update(ObjectVisitItemsSyncData $data, CleaningObject $object): RedirectResponse
    {
        $this->items->sync($object, $data);

        return back()->with('success', __('app.object_visit_items_saved'));
    }
}
