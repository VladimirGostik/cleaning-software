import { describe, expect, it } from 'vitest';

import { formatDate, formatDatetime, formatMonth, formatShortDatetime, monthOptions, previousMonth } from './date';

describe('date formats', () => {
    it('formats a date without zero padding', () => {
        expect(formatDate('2026-11-01')).toBe('1. 11. 2026');
        expect(formatDate(null)).toBe('—');
    });

    it('formats a datetime with unpadded hours', () => {
        expect(formatDatetime('2026-11-01T07:30:00')).toBe('1. 11. 2026 7:30');
    });

    it('shows time for today and date otherwise', () => {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 7, 30);
        expect(formatShortDatetime(today)).toBe('7:30');
        expect(formatShortDatetime('2020-03-05T07:30:00')).toBe('5. 3.');
    });
});

describe('month helpers', () => {
    it('formats a month without zero padding', () => {
        expect(formatMonth('2026-10')).toBe('10/2026');
        expect(formatMonth('2026-03-01')).toBe('3/2026');
        expect(formatMonth(null)).toBe('—');
        expect(formatMonth('')).toBe('—');
    });

    it('returns the previous month, rolling January back to December', () => {
        expect(previousMonth(new Date(2026, 10, 15))).toBe('2026-10');
        expect(previousMonth(new Date(2026, 0, 3))).toBe('2025-12');
    });

    it('lists months newest first starting with the current one', () => {
        const options = monthOptions('sk', 18, null, new Date(2026, 10, 15));

        expect(options).toHaveLength(18);
        expect(options[0]?.value).toBe('2026-11');
        expect(options[1]?.value).toBe('2026-10');
        expect(options[17]?.value).toBe('2025-06');
        expect(options[1]?.label).toBe('október 2026');
    });

    it('adds a month outside the range in order', () => {
        const options = monthOptions('sk', 3, '2024-05', new Date(2026, 10, 15));

        expect(options.map((o) => o.value)).toEqual(['2026-11', '2026-10', '2026-09', '2024-05']);
    });

    it('does not duplicate an included month already in range', () => {
        expect(monthOptions('sk', 3, '2026-10', new Date(2026, 10, 15))).toHaveLength(3);
    });
});
