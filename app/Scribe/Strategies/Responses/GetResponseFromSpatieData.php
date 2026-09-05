<?php

declare(strict_types=1);

namespace App\Scribe\Strategies\Responses;

use App\Scribe\Attributes\ResponseFromSpatieData as ResponseFromSpatieDataAttr;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\DatabaseTransactionHelpers;
use Knuckles\Scribe\Extracting\InstantiatesExampleModels;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use Throwable;

final class GetResponseFromSpatieData extends Strategy
{
    use DatabaseTransactionHelpers;
    use InstantiatesExampleModels;

    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        $reflAttributes = $endpointData->method->getAttributes(ResponseFromSpatieDataAttr::class);

        if (empty($reflAttributes)) {
            return null;
        }

        $responses = [];

        foreach ($reflAttributes as $reflAttr) {
            /** @var ResponseFromSpatieDataAttr $attr */
            $attr = $reflAttr->newInstance();

            $this->startDbTransaction();

            try {
                // Pass empty relations to avoid factory trying to create role/permission models
                $model = $this->instantiateExampleModel($attr->model, $attr->states, []);
                if (! empty($attr->with) && $model !== null) {
                    $model->load($attr->with);
                }
                $data = ($attr->dataClass)::fromModel($model);
                $dataArray = $data->toArray();

                $content = $attr->paginated
                    ? $this->wrapInPaginator($dataArray)
                    : json_encode($dataArray, JSON_THROW_ON_ERROR);

                $responses[] = [
                    'status' => $attr->status,
                    'description' => $attr->description,
                    'content' => $content,
                ];
            } catch (Throwable) {
                // skip if model factory or fromModel() fails
            } finally {
                $this->endDbTransaction();
            }
        }

        return empty($responses) ? null : $responses;
    }

    private function wrapInPaginator(array $item): string
    {
        return json_encode([
            'current_page' => 1,
            'data' => [$item],
            'first_page_url' => '...',
            'from' => 1,
            'last_page' => 1,
            'last_page_url' => '...',
            'links' => [],
            'next_page_url' => null,
            'path' => '...',
            'per_page' => 25,
            'prev_page_url' => null,
            'to' => 1,
            'total' => 1,
        ], JSON_THROW_ON_ERROR);
    }
}
