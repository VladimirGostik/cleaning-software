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
        $this->assertSame('viewContractTemplates', PermissionEnum::ViewContractTemplates->sharedKey());
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
}
