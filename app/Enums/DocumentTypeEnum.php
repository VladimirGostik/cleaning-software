<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DocumentTypeEnum: string
{
    case ServiceAgreement = 'service_agreement';
    case Employment = 'employment';
    case Nda = 'nda';
    case Gdpr = 'gdpr';
    case Other = 'other';
    case Quote = 'quote';
    case EmployeeIdCard = 'employee_id_card';
    case EmployeePassport = 'employee_passport';
    case EmployeeResidencePermit = 'employee_residence_permit';
    case EmployeeWorkPermit = 'employee_work_permit';
    case EmployeeMedical = 'employee_medical';
    case EmployeeTraining = 'employee_training';
    case EmployeeOther = 'employee_other';

    public function label(): string
    {
        return __('app.document_type_'.$this->value);
    }

    public function isContract(): bool
    {
        return $this->contractCategory() !== null;
    }

    public function contractCategory(): ?ContractCategoryEnum
    {
        return ContractCategoryEnum::tryFrom($this->value);
    }

    public static function fromCategory(ContractCategoryEnum $category): self
    {
        return self::from($category->value);
    }

    /** @return list<self> */
    public static function templatable(): array
    {
        return [self::ServiceAgreement, self::Employment, self::Nda, self::Gdpr, self::Other, self::Quote];
    }

    /** @return list<self> */
    public static function employeeFiles(): array
    {
        return [
            self::EmployeeIdCard, self::EmployeePassport, self::EmployeeResidencePermit,
            self::EmployeeWorkPermit, self::EmployeeMedical, self::EmployeeTraining, self::EmployeeOther,
        ];
    }

    public function isEmployeeFile(): bool
    {
        return in_array($this, self::employeeFiles(), true);
    }
}
