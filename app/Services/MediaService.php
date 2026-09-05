<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\MediaDetailData;
use App\Data\MediaIndexFilterData;
use App\Utils\AllowedFilter;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class MediaService
{
    public function __construct(private MediaUrlResolver $urls) {}

    /** @return LengthAwarePaginator<Media> */
    public function index(MediaIndexFilterData $filter): LengthAwarePaginator
    {
        $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return QueryBuilder::for(Media::class)
            ->allowedFilters(
                AllowedFilter::callbackClean('search', function ($q, $v) use ($op): void {
                    if (blank($v)) {
                        return;
                    }
                    $q->where(function ($inner) use ($v, $op): void {
                        $inner->where('file_name', $op, "%{$v}%")
                            ->orWhere('name', $op, "%{$v}%");
                    });
                }),
                AllowedFilter::exact('model_type'),
                AllowedFilter::exact('collection_name'),
                AllowedFilter::callbackClean('mime_type', function ($q, $v) use ($op): void {
                    if (blank($v)) {
                        return;
                    }
                    if (str_ends_with($v, '/')) {
                        $q->where('mime_type', $op, "{$v}%");
                    } else {
                        $q->where('mime_type', '=', $v);
                    }
                }),
            )
            ->allowedSorts('created_at', 'file_name', 'size', 'mime_type')
            ->defaultSort('-created_at')
            ->paginate($filter->per_page ?? 20)
            ->withQueryString();
    }

    public function show(Media $media): MediaDetailData
    {
        $resolved = $this->urls->resolve($media->model_type, $media->model_id);

        return new MediaDetailData(
            id: (int) $media->id,
            uuid: $media->uuid,
            file_name: $media->file_name,
            name: $media->name,
            size: (int) $media->size,
            mime_type: $media->mime_type,
            collection_name: $media->collection_name,
            disk: $media->disk,
            custom_properties: $media->custom_properties ?? [],
            model_type: (string) $media->model_type,
            model_type_label: $resolved['label'],
            model_id: $media->model_id !== null ? (string) $media->model_id : null,
            model_url: $resolved['url'],
            url: $media->getFullUrl(),
            created_at: $media->created_at?->toIso8601String() ?? '',
        );
    }
}
