<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\MediaDetailData;
use App\Data\MediaIndexFilterData;
use App\Models\Media;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class MediaService
{
    public function __construct(private MediaUrlResolver $urls) {}

    /** @return LengthAwarePaginator<int, Media> */
    public function index(MediaIndexFilterData $filter): LengthAwarePaginator
    {
        $op = config('database.default') === 'pgsql' ? 'ilike' : 'like';

        return QueryBuilder::for(Media::inTenant(current_tenant_id()))
            ->allowedFilters(
                AllowedFilter::callbackClean('search', function (Builder $q, mixed $v) use ($op): void {
                    if (blank($v) || ! is_scalar($v)) {
                        return;
                    }

                    $like = '%'.Filters::escapeLikeValue((string) $v).'%';

                    $q->where(function (Builder $inner) use ($like, $op): void {
                        $inner->where('file_name', $op, $like)
                            ->orWhere('name', $op, $like);
                    });
                }),
                AllowedFilter::exact('model_type'),
                AllowedFilter::exact('collection_name'),
                AllowedFilter::callbackClean('mime_type', function (Builder $q, mixed $v) use ($op): void {
                    if (blank($v) || ! is_scalar($v)) {
                        return;
                    }

                    $value = (string) $v;

                    if (str_ends_with($value, '/')) {
                        $q->where('mime_type', $op, Filters::escapeLikeValue($value).'%');
                    } else {
                        $q->where('mime_type', '=', $value);
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

        /** @var array<string, mixed> $customProperties */
        $customProperties = $media->custom_properties ?? [];

        return new MediaDetailData(
            id: (int) $media->id,
            uuid: $media->uuid,
            file_name: $media->file_name,
            name: $media->name,
            size: (int) $media->size,
            mime_type: $media->mime_type,
            collection_name: $media->collection_name,
            disk: $media->disk,
            custom_properties: $customProperties,
            model_type: (string) $media->model_type,
            model_type_label: $resolved['label'],
            model_id: (string) $media->model_id,
            model_url: $resolved['url'],
            url: $media->getFullUrl(),
            created_at: $media->created_at?->toIso8601String() ?? '',
        );
    }
}
