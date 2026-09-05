<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\Contracts\ValidateableData;
use Spatie\LaravelData\Resolvers\DataValidatorResolver;

final class PrecognitiveDataValidatorResolver extends DataValidatorResolver
{
    /**
     * @param  class-string<ValidateableData&BaseData<mixed, mixed, array-key>>  $dataClass
     * @param  Arrayable<string, mixed>|array<string, mixed>  $payload
     */
    public function execute(string $dataClass, Arrayable|array $payload): Validator
    {
        $validator = parent::execute($dataClass, $payload);

        $request = request();

        if (! $request->isAttemptingPrecognition()) {
            return $validator;
        }

        $only = (string) $request->header('Precognition-Validate-Only', '');

        if ($only === '') {
            return $validator;
        }

        $fields = array_values(array_filter(array_map('trim', explode(',', $only))));

        if ($fields === []) {
            return $validator;
        }

        $filtered = [];

        foreach ($validator->getRules() as $key => $rule) {
            foreach ($fields as $field) {
                if ($key === $field || str_starts_with($key, $field.'.')) {
                    $filtered[$key] = $rule;
                    break;
                }
            }
        }

        $validator->setRules($filtered);

        return $validator;
    }
}
