<?php

declare(strict_types=1);

namespace App\Services;

final readonly class MediaUrlResolver
{
    /** @return array{label: string, url: string|null} */
    public function resolve(?string $modelType, string|int|null $modelId): array
    {
        if ($modelType === null || $modelId === null) {
            return ['label' => $modelType !== null ? class_basename($modelType) : '—', 'url' => null];
        }

        $label = class_basename($modelType);

        /** @var array<class-string, array{name: string, param: string}> $map */
        $map = config('media-urls.models', []);

        if (! isset($map[$modelType])) {
            return ['label' => $label, 'url' => null];
        }

        $entry = $map[$modelType];

        return [
            'label' => $label,
            'url' => route($entry['name'], [$entry['param'] => $modelId]),
        ];
    }
}
