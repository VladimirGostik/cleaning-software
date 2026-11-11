import { describe, expect, it } from 'vitest';
import {
    contractCancelHref,
    contractSignatureLabel,
    fileAccept,
    fileFlowAvailable,
    initialFileFormData,
} from './contracts';
import { formatDate, formatDatetime } from './date';

const t = (key: string) => key;

function prefill(
    overrides: Partial<App.Data.Contracts.ContractCreatePrefillData> = {},
): App.Data.Contracts.ContractCreatePrefillData {
    return {
        contractable_type: null,
        contractable_id: null,
        contractable_label: null,
        category: null,
        employment_type: null,
        document_template_id: null,
        ...overrides,
    };
}

describe('contractSignatureLabel', () => {
    it('shows the paper date of a signed file contract', () => {
        const label = contractSignatureLabel(t, {
            source: 'file',
            is_signed: true,
            signed_at: '2026-03-01T00:00:00+01:00',
        });

        expect(label).toBe(formatDate('2026-03-01'));
    });

    it('shows an unknown date for a signed file contract without date', () => {
        expect(contractSignatureLabel(t, { source: 'file', is_signed: true, signed_at: null })).toBe(
            'contract_signed_date_unknown',
        );
    });

    it('shows an unsigned draft for an unsigned file contract', () => {
        expect(contractSignatureLabel(t, { source: 'file', is_signed: false, signed_at: null })).toBe(
            'contract_unsigned_draft',
        );
    });

    it('keeps the generated behaviour', () => {
        expect(
            contractSignatureLabel(t, { source: 'generated', is_signed: true, signed_at: '2026-03-01T10:30:00+01:00' }),
        ).toBe(formatDatetime('2026-03-01T10:30:00+01:00'));
        expect(contractSignatureLabel(t, { source: 'generated', is_signed: false, signed_at: null })).toBe(
            'contract_not_signed',
        );
    });
});

describe('fileFlowAvailable', () => {
    it('is available without prefill and for object and client', () => {
        expect(fileFlowAvailable(null)).toBe(true);
        expect(fileFlowAvailable(prefill({ contractable_type: 'cleaning_object' }))).toBe(true);
        expect(fileFlowAvailable(prefill({ contractable_type: 'client' }))).toBe(true);
    });

    it('is not available for an employee', () => {
        expect(fileFlowAvailable(prefill({ contractable_type: 'tenant_membership' }))).toBe(false);
    });
});

describe('initialFileFormData', () => {
    it('uses the object prefill', () => {
        const data = initialFileFormData(prefill({ contractable_type: 'cleaning_object', contractable_id: 'o-1' }));

        expect(data).toMatchObject({
            contractable_type: 'cleaning_object',
            contractable_id: 'o-1',
            category: 'service_agreement',
            is_signed: true,
            signed_on: null,
            file_uuid: null,
        });
    });

    it('falls back from a category the file flow does not offer', () => {
        expect(initialFileFormData(prefill({ category: 'employment' })).category).toBe('service_agreement');
        expect(initialFileFormData(prefill({ category: 'nda' })).category).toBe('nda');
    });

    it('takes the date part of signed_at from a contract', () => {
        const contract = {
            id: 'k-1',
            title: 'T',
            number: null,
            category: 'nda',
            term_type: 'fixed',
            contractable_type: 'client',
            contractable_id: 'c-1',
            valid_from: '2026-01-01',
            end_date: '2026-12-31',
            is_signed: true,
            signed_at: '2026-02-03T00:00:00+01:00',
            notes: 'n',
        } as App.Data.Contracts.ContractDetailData;

        expect(initialFileFormData(contract)).toMatchObject({
            signed_on: '2026-02-03',
            end_date: '2026-12-31',
            file_uuid: null,
        });
    });
});

describe('contractCancelHref', () => {
    it('goes to the contract when editing', () => {
        expect(contractCancelHref({ id: 'k-1' }, null)).toBe('/contracts/k-1');
    });

    it('goes back to the subject detail', () => {
        expect(contractCancelHref(null, prefill({ contractable_type: 'client', contractable_id: 'c' }))).toBe(
            '/clients/c',
        );
        expect(contractCancelHref(null, prefill({ contractable_type: 'cleaning_object', contractable_id: 'o' }))).toBe(
            '/objects/o',
        );
        expect(
            contractCancelHref(null, prefill({ contractable_type: 'tenant_membership', contractable_id: 'e' })),
        ).toBe('/employees/e');
    });

    it('falls back to the list', () => {
        expect(contractCancelHref(null, null)).toBe('/contracts');
        expect(contractCancelHref(null, prefill({ contractable_type: 'client' }))).toBe('/contracts');
    });
});

describe('fileAccept', () => {
    it('lists mimes and dot-prefixed extensions once', () => {
        expect(fileAccept({ allowed_mimes: ['application/pdf'], allowed_extensions: ['pdf', '.docx'] })).toBe(
            'application/pdf,.pdf,.docx',
        );
    });

    it('works without extensions', () => {
        expect(fileAccept({ allowed_mimes: ['a/b', 'a/b'] })).toBe('a/b');
    });
});
