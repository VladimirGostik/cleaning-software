<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CleaningObject;
use App\Models\Tenant;
use App\Models\TenantMembership;

final readonly class PlaceholderResolverService
{
    /**
     * Replaces {{token}} occurrences in $body with values from $variables map.
     * Unknown tokens are left as-is for user manual completion.
     *
     * @param  array<string, string|null>  $variables
     */
    public function resolve(string $body, array $variables): string
    {
        foreach ($variables as $token => $value) {
            $body = str_replace('{{' . $token . '}}', $value ?? '', $body);
        }

        return $body;
    }

    /**
     * Returns variable map from CleaningObject + its Client + current Tenant.
     *
     * @return array<string, string|null>
     */
    public function variablesForCleaningObject(CleaningObject $object, Tenant $tenant): array
    {
        $object->loadMissing(['client']);

        return [
            'tenant.name' => $tenant->name,
            'tenant.ico' => $tenant->ico,
            'tenant.dic' => $tenant->dic,
            'tenant.address' => trim($tenant->address_line . ', ' . $tenant->city),
            'tenant.iban' => $tenant->iban,
            'client.name' => $object->client?->name,
            'client.ico' => $object->client?->ico,
            'client.dic' => $object->client?->dic,
            'object.name' => $object->name,
            'object.address' => trim($object->street . ', ' . $object->city),
        ];
    }

    /**
     * Returns variable map from TenantMembership + its User + current Tenant.
     *
     * @return array<string, string|null>
     */
    public function variablesForMembership(TenantMembership $membership, Tenant $tenant): array
    {
        $membership->loadMissing(['user']);

        return [
            'tenant.name' => $tenant->name,
            'tenant.ico' => $tenant->ico,
            'tenant.address' => trim($tenant->address_line . ', ' . $tenant->city),
            'employee.name' => $membership->user?->name,
            'employee.email' => $membership->user?->email,
        ];
    }

    /**
     * Returns token catalog for FE "insert variable" picker.
     *
     * @return array<int, array{token: string, label: string}>
     */
    public function catalogFor(string $contractableType): array
    {
        $keys = match ($contractableType) {
            'cleaning_object' => [
                'tenant.name', 'tenant.ico', 'tenant.dic', 'tenant.address', 'tenant.iban',
                'client.name', 'client.ico', 'client.dic', 'object.name', 'object.address',
            ],
            'tenant_membership' => [
                'tenant.name', 'tenant.ico', 'tenant.address',
                'employee.name', 'employee.email',
            ],
            default => [],
        };

        return array_map(
            fn (string $key) => [
                'token' => '{{' . $key . '}}',
                'label' => __('app.contract_token.' . str_replace('.', '_', $key)),
            ],
            $keys,
        );
    }
}
