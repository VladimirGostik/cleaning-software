import { describe, expect, it } from 'vitest';

import {
    currentMonth,
    formatDate,
    formatMonthLabel,
    formatDatetime,
    formatMonth,
    formatRelativeTime,
    formatShortDatetime,
    localDatetimeToIso,
    monthOptions,
    previousMonth,
    shiftMonth,
} from './date';

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

describe('localDatetimeToIso', () => {
    it('returns null for an empty value', () => {
        expect(localDatetimeToIso('', '2026-11-01T07:30:00Z')).toBeNull();
    });

    it('keeps the original when the local value did not change', () => {
        expect(localDatetimeToIso('2026-11-01T07:30', '2026-11-01T07:30:00')).toBe('2026-11-01T07:30:00');
    });

    it('converts a changed local value to ISO', () => {
        expect(localDatetimeToIso('2026-11-02T08:00', '2026-11-01T07:30:00')).toBe(
            new Date('2026-11-02T08:00').toISOString(),
        );
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

describe('formatRelativeTime', () => {
    const now = new Date(2026, 10, 13, 12, 0, 0);
    const rtf = (locale: string) => new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    it('says now under a minute', () => {
        expect(formatRelativeTime(new Date(2026, 10, 13, 11, 59, 40).toISOString(), now, 'en')).toBe(
            rtf('en-GB').format(0, 'second'),
        );
    });

    it('uses minutes and hours', () => {
        expect(formatRelativeTime(new Date(2026, 10, 13, 11, 55).toISOString(), now, 'sk')).toBe(
            rtf('sk-SK').format(-5, 'minute'),
        );
        expect(formatRelativeTime(new Date(2026, 10, 13, 9, 0).toISOString(), now, 'uk')).toBe(
            rtf('uk-UA').format(-3, 'hour'),
        );
    });

    it('says yesterday for the previous calendar day', () => {
        expect(formatRelativeTime(new Date(2026, 10, 12, 8, 0).toISOString(), now, 'sk')).toBe(
            rtf('sk-SK').format(-1, 'day'),
        );
    });

    it('falls back to a short date this year and a full date before', () => {
        expect(formatRelativeTime(new Date(2026, 8, 3, 8, 0).toISOString(), now, 'sk')).toBe('3. 9.');
        expect(formatRelativeTime(new Date(2025, 8, 3, 8, 0).toISOString(), now, 'sk')).toBe('3. 9. 2025');
    });
});

describe('month helpers', () => {
    it('shifts across year boundaries', () => {
        expect(shiftMonth('2026-01', -1)).toBe('2025-12');
        expect(shiftMonth('2026-12', 1)).toBe('2027-01');
        expect(shiftMonth('2026-11', 0)).toBe('2026-11');
    });

    it('reads the current month from a date', () => {
        expect(currentMonth(new Date(2026, 10, 13))).toBe('2026-11');
    });

    it('labels a month capitalised in the locale', () => {
        expect(formatMonthLabel('2026-11', 'en')).toBe('November 2026');
    });
});
