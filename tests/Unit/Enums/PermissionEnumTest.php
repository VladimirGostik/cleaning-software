<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\PermissionEnum;
use PHPUnit\Framework\TestCase;

final class PermissionEnumTest extends TestCase
{
    public function test_permission_enum_contains_all_client_policy_permissions(): void
    {
        // The four permissions ClientPolicy uses via PermissionEnum
        $requiredValues = [
            'view clients',
            'create clients',
            'edit clients',
            'delete clients',
        ];

        $enumValues = PermissionEnum::values();

        foreach ($requiredValues as $required) {
            $this->assertContains(
                $required,
                $enumValues,
                "PermissionEnum must contain '{$required}' — ClientPolicy depends on it",
            );
        }
    }

    public function test_permission_enum_cases_are_unique(): void
    {
        $values = PermissionEnum::values();
        $unique = array_unique($values);

        $this->assertCount(
            count($unique),
            $values,
            'PermissionEnum must not contain duplicate permission strings',
        );
    }

    public function test_permission_enum_values_helper_returns_all_cases(): void
    {
        $this->assertCount(
            count(PermissionEnum::cases()),
            PermissionEnum::values(),
            'PermissionEnum::values() must return exactly one entry per case',
        );
    }

    public function test_view_clients_case_name_and_value(): void
    {
        $this->assertSame('view clients', PermissionEnum::ViewClients->value);
    }

    public function test_create_clients_case_name_and_value(): void
    {
        $this->assertSame('create clients', PermissionEnum::CreateClients->value);
    }

    public function test_edit_clients_case_name_and_value(): void
    {
        $this->assertSame('edit clients', PermissionEnum::EditClients->value);
    }

    public function test_delete_clients_case_name_and_value(): void
    {
        $this->assertSame('delete clients', PermissionEnum::DeleteClients->value);
    }
}
