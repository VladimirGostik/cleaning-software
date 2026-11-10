<?php

declare(strict_types=1);

namespace App\Services\Expenses;

use App\Data\Expenses\ExpenseImportAccountCheckData;
use App\Enums\ExpenseImportAccountStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantPermissionSnapshot;

final readonly class StatementAccountCheck
{
    public function __construct(private TenantPermissionSnapshot $snapshot) {}

    public function check(string $iban, User $actor, PermissionEnum $required): ExpenseImportAccountCheckData
    {
        $active = Tenant::query()->findOrFail(current_tenant_id());

        if ($this->sameIban($active->iban, $iban)) {
            return new ExpenseImportAccountCheckData(ExpenseImportAccountStatusEnum::Matched, $iban);
        }

        foreach ($this->snapshot->forRequest($actor) as $visibility) {
            if ($visibility->tenant->is_active && in_array($required, $visibility->permissions, true) && $this->sameIban($visibility->tenant->iban, $iban)) {
                return new ExpenseImportAccountCheckData(
                    ExpenseImportAccountStatusEnum::OtherCompany,
                    $iban,
                    $visibility->tenant->id,
                    $visibility->tenant->name,
                );
            }
        }

        $status = trim((string) $active->iban) === ''
            ? ExpenseImportAccountStatusEnum::CompanyIbanMissing
            : ExpenseImportAccountStatusEnum::UnknownAccount;

        return new ExpenseImportAccountCheckData($status, $iban);
    }

    private function sameIban(?string $firmIban, string $statementIban): bool
    {
        return $firmIban !== null && CamtStatementParser::normalizeIban($firmIban) === $statementIban;
    }
}
