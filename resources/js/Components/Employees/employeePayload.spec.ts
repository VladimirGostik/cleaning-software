import { describe, expect, it } from 'vitest';
import { employeeStorePayload, employeeUpdatePayload, type EmployeeFormData } from './employeePayload';

function data(overrides: Partial<EmployeeFormData> = {}): EmployeeFormData {
    return {
        has_app_access: false,
        employee_type: 'brigade',
        email: '',
        contact_email: null,
        first_name: 'Jana',
        last_name: 'Nováková',
        phone: '',
        position: null,
        role_name: '',
        permissions: [],
        employment: null,
        company_ico: null,
        company_name: null,
        birth_date: '1990-01-01',
        national_id: '900101/1234',
        street: 'Hlavná 1',
        city: 'Bratislava',
        postal_code: '81101',
        ...overrides,
    };
}

describe('employeeStorePayload', () => {
    it('sends no role and a null email when access is off', () => {
        const payload = employeeStorePayload(data({ role_name: 'Admin', permissions: ['view tasks'] }), true);

        expect(payload.has_app_access).toBe(false);
        expect(payload.role_name).toBeNull();
        expect(payload.permissions).toEqual([]);
        expect(payload.email).toBeNull();
        expect(payload.phone).toBeNull();
    });

    it('sends email, role and type when access is on', () => {
        const payload = employeeStorePayload(
            data({ has_app_access: true, email: 'a@b.sk', role_name: 'Admin', employee_type: 'tpp' }),
            false,
        );

        expect(payload).toMatchObject({
            has_app_access: true,
            email: 'a@b.sk',
            role_name: 'Admin',
            employee_type: 'tpp',
        });
    });
});

describe('employeeUpdatePayload', () => {
    it('never contains email or has_app_access', () => {
        const payload = employeeUpdatePayload(data({ email: 'login@b.sk', contact_email: 'c@b.sk' }), true);

        expect(payload).not.toHaveProperty('email');
        expect(payload).not.toHaveProperty('has_app_access');
        expect(payload.contact_email).toBe('c@b.sk');
    });

    it('keeps a null type null', () => {
        expect(employeeUpdatePayload(data({ employee_type: null }), false).employee_type).toBeNull();
    });
});

describe('both payloads', () => {
    it.each([
        ['store', employeeStorePayload],
        ['update', employeeUpdatePayload],
    ])('%s always carries the company keys and omits personal data without permission', (_name, build) => {
        const payload = build(data(), false);

        expect(payload).toHaveProperty('company_ico', null);
        expect(payload).toHaveProperty('company_name', null);
        for (const key of ['birth_date', 'national_id', 'street', 'city', 'postal_code']) {
            expect(payload).not.toHaveProperty(key);
        }
    });

    it.each([
        ['store', employeeStorePayload],
        ['update', employeeUpdatePayload],
    ])('%s includes personal data with permission', (_name, build) => {
        expect(build(data({ street: '' }), true)).toMatchObject({ birth_date: '1990-01-01', street: null });
    });
});
