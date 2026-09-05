<?php

declare(strict_types=1);

namespace App\Scribe\Strategies\BodyParameters;

use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Extracting\ParsesValidationRules;
use Knuckles\Scribe\Extracting\Strategies\Strategy;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Spatie\LaravelData\Attributes\Validation\ValidationAttribute;
use Spatie\LaravelData\Data;

final class GetBodyParamsFromSpatieData extends Strategy
{
    use ParsesValidationRules;

    public function __invoke(ExtractedEndpointData $endpointData, array $settings = []): ?array
    {
        $dataClass = $this->findDataClass($endpointData);

        if ($dataClass === null) {
            return null;
        }

        $rules = $this->rulesFromConstructor($dataClass);

        if (empty($rules)) {
            return null;
        }

        return $this->normaliseArrayAndObjectParameters(
            $this->getParametersFromValidationRules($rules, []),
        );
    }

    private function findDataClass(ExtractedEndpointData $endpointData): ?string
    {
        foreach ($endpointData->method->getParameters() as $param) {
            $type = $param->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $name = $type->getName();

            if (class_exists($name) && is_subclass_of($name, Data::class)) {
                return $name;
            }
        }

        return null;
    }

    private function rulesFromConstructor(string $dataClass): array
    {
        $constructor = (new ReflectionClass($dataClass))->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $rules = [];

        foreach ($constructor->getParameters() as $param) {
            $fieldRules = $this->rulesForParam($param);

            if (! empty($fieldRules)) {
                $rules[$param->getName()] = $fieldRules;
            }
        }

        return $rules;
    }

    /** @return string[] */
    private function rulesForParam(ReflectionParameter $param): array
    {
        $rules = [];

        // Spatie validation attributes → string via __toString()
        foreach ($param->getAttributes() as $attr) {
            $instance = $attr->newInstance();

            if ($instance instanceof ValidationAttribute) {
                $str = (string) $instance;
                foreach (explode('|', $str) as $rule) {
                    $rule = trim($rule);
                    if ($rule !== '') {
                        $rules[] = $rule;
                    }
                }
            }
        }

        // Type-inferred rules (only when no explicit validation attributes set the type)
        $type = $param->getType();
        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            $typeRule = match ($typeName) {
                'bool' => 'boolean',
                'int' => 'integer',
                'float' => 'numeric',
                'array' => 'array',
                'string' => 'string',
                default => null,
            };
            if ($typeRule !== null && ! in_array($typeRule, $rules, true)) {
                array_unshift($rules, $typeRule);
            }
        }

        // required / optional
        if (! $param->isOptional() && ! in_array('required', $rules, true)) {
            array_unshift($rules, 'required');
        } elseif ($param->isOptional()) {
            $rules[] = 'sometimes';
        }

        return $rules;
    }
}
