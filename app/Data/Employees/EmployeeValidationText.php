<?php

declare(strict_types=1);

namespace App\Data\Employees;

final class EmployeeValidationText
{
    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'employee_type.required' => __('app.employee_validation_type_required'),
            'employee_type.enum' => __('app.employee_validation_type_required'),
            'first_name.string' => __('app.employee_validation_first_name_required'),
            'first_name.required' => __('app.employee_validation_first_name_required'),
            'first_name.required_if' => __('app.employee_validation_first_name_required'),
            'last_name.string' => __('app.employee_validation_last_name_required'),
            'last_name.required' => __('app.employee_validation_last_name_required'),
            'last_name.required_if' => __('app.employee_validation_last_name_required'),
            'email.string' => __('app.employee_validation_email_required'),
            'email.required' => __('app.employee_validation_email_required'),
            'email.required_if' => __('app.employee_validation_email_required'),
            'email.email' => __('app.employee_validation_email_invalid'),
            'contact_email.email' => __('app.employee_validation_email_invalid'),
            'role_name.string' => __('app.employee_validation_role_required'),
            'role_name.required' => __('app.employee_validation_role_required'),
            'role_name.required_if' => __('app.employee_validation_role_required'),
            'role_name.exists' => __('app.employee_validation_role_invalid'),
            'company_ico.string' => __('app.employee_validation_ico_required'),
            'company_ico.required_if' => __('app.employee_validation_ico_required'),
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'employee_type' => __('app.employee_attr_employee_type'),
            'first_name' => __('app.employee_attr_first_name'),
            'last_name' => __('app.employee_attr_last_name'),
            'email' => __('app.employee_attr_email'),
            'contact_email' => __('app.employee_attr_contact_email'),
            'role_name' => __('app.employee_attr_role_name'),
            'company_ico' => __('app.employee_attr_company_ico'),
            'company_name' => __('app.employee_attr_company_name'),
            'has_app_access' => __('app.employee_attr_has_app_access'),
        ];
    }
}
