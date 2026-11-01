import { describe, expect, it } from 'vitest';

import { formatDate, formatDatetime, formatShortDatetime } from './date';

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
