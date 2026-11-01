<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\PermissionEnum;
use PHPUnit\Framework\TestCase;

final class PermissionEnumTest extends TestCase
{
    public function test_every_case_has_a_non_empty_shared_key(): void
    {
        foreach (PermissionEnum::cases() as $case) {
            $this->assertNotSame('', $case->sharedKey());
            $this->assertDoesNotMatchRegularExpression('/[\s_]/', $case->sharedKey());
        }
    }

    public function test_shared_key_camel_cases_multi_word_permissions(): void
    {
        $this->assertSame('viewAllObjects', PermissionEnum::ViewAllObjects->sharedKey());
        $this->assertSame('viewDocumentTemplates', PermissionEnum::ViewDocumentTemplates->sharedKey());
        $this->assertSame('viewApiDocs', PermissionEnum::ViewApiDocs->sharedKey());
    }

    public function test_every_case_resolves_to_a_non_empty_group(): void
    {
        foreach (PermissionEnum::cases() as $case) {
            $this->assertNotSame('', $case->group());
        }
    }

    public function test_group_splits_invoices_and_billing(): void
    {
        $this->assertSame('invoices', PermissionEnum::ViewInvoices->group());
        $this->assertSame('billing', PermissionEnum::ManageBillingSettings->group());
    }

    public function test_values_are_unique(): void
    {
        $values = PermissionEnum::values();

        $this->assertCount(count($values), array_unique($values));
        $this->assertCount(count(PermissionEnum::cases()), $values);
    }

    public function test_values_are_kebab_case_with_spaces_not_underscores(): void
    {
        foreach (PermissionEnum::values() as $value) {
            $this->assertDoesNotMatchRegularExpression('/[A-Z]/', $value);
        }
    }

    public function test_mobile_tasks_and_expenses_permissions_are_grouped(): void
    {
        $this->assertSame('mobile', PermissionEnum::AccessMobileApp->group());
        $this->assertSame('tasks', PermissionEnum::ViewAllTasks->group());
        $this->assertSame('expenses', PermissionEnum::ManageExpenses->group());
        $this->assertSame('viewAllTasks', PermissionEnum::ViewAllTasks->sharedKey());
    }

    public function test_manage_employee_access_is_grouped_with_employees(): void
    {
        $this->assertSame('employees', PermissionEnum::ManageEmployeeAccess->group());
        $this->assertSame('manage employee_access', PermissionEnum::ManageEmployeeAccess->value);
        $this->assertSame('manageEmployeeAccess', PermissionEnum::ManageEmployeeAccess->sharedKey());
    }

    public function test_use_assistant_is_grouped_and_exposed_under_its_shared_key(): void
    {
        $this->assertSame('use assistant', PermissionEnum::UseAssistant->value);
        $this->assertSame('ai', PermissionEnum::UseAssistant->group());
        $this->assertSame('useAssistant', PermissionEnum::UseAssistant->sharedKey());
    }
}
