import { describe, expect, it } from 'vitest';
import { EMPLOYEE_TYPES, employeeTypeKey, employmentTypeForEmployeeType } from './enums';

describe('employee type helpers', () => {
    it('lists exactly the three employee types', () => {
        expect([...EMPLOYEE_TYPES]).toEqual(['brigade', 'tpp', 'self_employed']);
    });

    it('maps an employee type to the matching contract kind', () => {
        expect(employmentTypeForEmployeeType('brigade')).toBe('dpp');
        expect(employmentTypeForEmployeeType('tpp')).toBe('tpp');
        expect(employmentTypeForEmployeeType('self_employed')).toBe('self_employed');
    });

    it('builds the translation key', () => {
        expect(employeeTypeKey('self_employed')).toBe('employee_type_self_employed');
    });
});
