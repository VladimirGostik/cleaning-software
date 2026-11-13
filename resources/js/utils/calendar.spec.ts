import { describe, expect, it } from 'vitest';
import {
    moveFocus,
    monthMatrix,
    nextMondayOf,
    parseTypedDate,
    parseTypedTime,
    toIsoDate,
    tomorrowOf,
    withinRange,
} from './calendar';

const today = new Date(2026, 10, 13);

describe('monthMatrix', () => {
    it('is 6 weeks of 7 days starting on Monday', () => {
        const matrix = monthMatrix(new Date(2026, 10, 1));

        expect(matrix).toHaveLength(6);
        expect(matrix.every((row) => row.length === 7)).toBe(true);
        expect(toIsoDate(matrix[0]![0]!)).toBe('2026-10-26');
        expect(toIsoDate(matrix[5]![6]!)).toBe('2026-12-06');
    });

    it('starts on the 1st when it is a Monday', () => {
        expect(toIsoDate(monthMatrix(new Date(2026, 5, 15))[0]![0]!)).toBe('2026-06-01');
    });

    it('keeps one calendar day per cell across the March DST change', () => {
        const days = monthMatrix(new Date(2026, 2, 1))
            .flat()
            .map(toIsoDate);

        expect(days).toContain('2026-03-29');
        expect(new Set(days).size).toBe(42);
        expect(days.slice(0, 3)).toEqual(['2026-02-23', '2026-02-24', '2026-02-25']);
    });

    it('keeps one calendar day per cell across the October DST change', () => {
        const days = monthMatrix(new Date(2026, 9, 1))
            .flat()
            .map(toIsoDate);

        expect(days).toContain('2026-10-25');
        expect(new Set(days).size).toBe(42);
    });

    it('includes 29 February in a leap year', () => {
        const days = monthMatrix(new Date(2028, 1, 1))
            .flat()
            .map(toIsoDate);

        expect(days).toContain('2028-02-29');
    });
});

describe('moveFocus', () => {
    const day = new Date(2026, 10, 13);

    it.each([
        ['ArrowLeft', false, '2026-11-12'],
        ['ArrowRight', false, '2026-11-14'],
        ['ArrowUp', false, '2026-11-06'],
        ['ArrowDown', false, '2026-11-20'],
        ['Home', false, '2026-11-09'],
        ['End', false, '2026-11-15'],
        ['PageUp', false, '2026-10-13'],
        ['PageDown', false, '2026-12-13'],
        ['PageUp', true, '2025-11-13'],
        ['PageDown', true, '2027-11-13'],
        ['x', false, '2026-11-13'],
    ])('%s (shift %s) -> %s', (key, shift, expected) => {
        expect(toIsoDate(moveFocus(day, key, shift))).toBe(expected);
    });

    it('clamps the month jump to the last day', () => {
        expect(toIsoDate(moveFocus(new Date(2026, 0, 31), 'PageDown'))).toBe('2026-02-28');
    });
});

describe('parseTypedDate', () => {
    it.each([
        ['13.11.2026', '2026-11-13'],
        ['13. 11. 2026', '2026-11-13'],
        ['1.2.2026', '2026-02-01'],
        ['13.11.', '2026-11-13'],
        ['13.11', '2026-11-13'],
        ['13.11.26', '2026-11-13'],
        ['14.3.84', '2084-03-14'],
        ['13112026', '2026-11-13'],
        ['29.2.2028', '2028-02-29'],
        ['14.3.1984', '1984-03-14'],
    ])('%s -> %s', (text, expected) => {
        expect(parseTypedDate(text, today)).toBe(expected);
    });

    it.each(['', 'abc', '32.1.2026', '31.2.2026', '29.2.2027', '13.13.2026', '13.11.202', '1311202', '0.1.2026'])(
        'rejects %s',
        (text) => {
            expect(parseTypedDate(text, today)).toBeNull();
        },
    );
});

describe('parseTypedTime', () => {
    it.each([
        ['9', '09:00'],
        ['930', '09:30'],
        ['0930', '09:30'],
        ['9:30', '09:30'],
        ['9.30', '09:30'],
        ['09:30', '09:30'],
        ['23:59', '23:59'],
        ['0', '00:00'],
        ['1730', '17:30'],
    ])('%s -> %s', (text, expected) => {
        expect(parseTypedTime(text)).toBe(expected);
    });

    it.each(['', 'abc', '24:00', '25:00', '9:60', '2400', '12345', '9:3'])('rejects %s', (text) => {
        expect(parseTypedTime(text)).toBeNull();
    });
});

describe('withinRange', () => {
    it('honours min and max inclusively', () => {
        expect(withinRange('2026-11-13', '2026-11-13', '2026-11-13')).toBe(true);
        expect(withinRange('2026-11-12', '2026-11-13')).toBe(false);
        expect(withinRange('2026-11-14', undefined, '2026-11-13')).toBe(false);
        expect(withinRange('2026-11-14')).toBe(true);
        expect(withinRange('09:30', '10:00')).toBe(false);
    });
});

describe('quick picks', () => {
    it('computes tomorrow and the next Monday', () => {
        expect(tomorrowOf(today)).toBe('2026-11-14');
        expect(nextMondayOf(today)).toBe('2026-11-16');
        expect(nextMondayOf(new Date(2026, 10, 16))).toBe('2026-11-23');
    });
});
