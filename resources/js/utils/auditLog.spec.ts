import { describe, expect, it } from 'vitest';
import { formatAuditValue, revertConfirmKey, revertSubjectLabel } from './auditLog';
import { formatDatetime } from './date';

const t = (key: string): string => key;

describe('formatAuditValue', () => {
    it('renders a dash for empty values', () => {
        expect(formatAuditValue(null, t)).toBe('—');
        expect(formatAuditValue(undefined, t)).toBe('—');
        expect(formatAuditValue('', t)).toBe('—');
    });

    it('translates booleans', () => {
        expect(formatAuditValue(true, t)).toBe('yes');
        expect(formatAuditValue(false, t)).toBe('no');
    });

    it('formats ISO dates and datetimes', () => {
        expect(formatAuditValue('2026-11-03T10:15:00Z', t)).toBe(formatDatetime('2026-11-03T10:15:00Z'));
        expect(formatAuditValue('2026-11-03', t)).toBe('3. 11. 2026');
    });

    it('passes numbers and strings through', () => {
        expect(formatAuditValue(12.5, t)).toBe('12.5');
        expect(formatAuditValue(0, t)).toBe('0');
        expect(formatAuditValue('abc', t)).toBe('abc');
    });

    it('serialises objects and arrays', () => {
        expect(formatAuditValue({ a: 1 }, t)).toBe('{"a":1}');
        expect(formatAuditValue([1, 2], t)).toBe('[1,2]');
    });
});

describe('revertConfirmKey', () => {
    it('maps supported events', () => {
        for (const event of ['created', 'updated', 'deleted', 'restored']) {
            expect(revertConfirmKey(event)).toBe(`audit_revert_confirm_${event}`);
        }
    });

    it('falls back to generic', () => {
        expect(revertConfirmKey(null)).toBe('audit_revert_confirm_generic');
        expect(revertConfirmKey('login')).toBe('audit_revert_confirm_generic');
    });
});

describe('revertSubjectLabel', () => {
    it('prefers the label', () => {
        expect(revertSubjectLabel({ subject_label: 'Acme', subject_type: 'Client', subject_id: '1' })).toBe('Acme');
    });

    it('falls back to type and id', () => {
        expect(revertSubjectLabel({ subject_label: null, subject_type: 'Client', subject_id: '1' })).toBe('Client #1');
        expect(revertSubjectLabel({ subject_label: null, subject_type: null, subject_id: null })).toBe('- #-');
    });
});
