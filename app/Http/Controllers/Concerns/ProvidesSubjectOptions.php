<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Data\Clients\ClientOptionData;
use App\Data\Objects\ObjectOptionData;
use App\Models\CleaningObject;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

/**
 * Client/object dropdown option builders shared by controllers that offer a
 * client + object subject picker (invoices, recurring invoices, quotes, contracts).
 */
trait ProvidesSubjectOptions
{
    /** @return array<int, ClientOptionData> */
    private function clientOptions(): array
    {
        return Client::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => ClientOptionData::fromModel($client))
            ->all();
    }

    /** @return array<int, ObjectOptionData> */
    private function objectOptions(?string $keepObjectId = null): array
    {
        return CleaningObject::query()
            ->with('client:id,name')
            ->where(function (Builder $query) use ($keepObjectId): void {
                $query->where('is_active', true);

                if ($keepObjectId !== null) {
                    $query->orWhere('id', $keepObjectId);
                }
            })
            ->orderBy('name')
            ->get()
            ->map(fn (CleaningObject $object) => ObjectOptionData::fromModel($object))
            ->all();
    }
}
