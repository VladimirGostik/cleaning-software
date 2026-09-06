<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\MediaIndexFilterData;
use App\Data\MediaListItemData;
use App\Enums\PermissionEnum;
use App\Models\Media;
use App\Navigation\NavItem;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class MediaController extends Controller
{
    public function __construct(private readonly MediaService $service) {}

    #[Authorize('viewAny', Media::class)]
    #[NavItem(label: 'app.media', route: 'media.index', icon: 'PhotoIcon', permission: PermissionEnum::ViewMedia->value, order: 50)]
    public function index(MediaIndexFilterData $filter, Request $request): Response
    {
        $paginator = $this->service->index($filter)
            ->through(fn (Media $m) => MediaListItemData::fromModel($m));

        return Inertia::render('Media/Index', [
            'media' => $paginator,
            'filters' => $request->query(),
        ]);
    }

    #[Authorize('view', 'media')]
    public function show(Media $media): Response
    {
        return Inertia::render('Media/Show', [
            'media' => $this->service->show($media),
        ]);
    }
}
