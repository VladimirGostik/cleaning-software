import { describe, expect, it } from 'vitest';
import { sameRange } from './calendarRange';

describe('sameRange', () => {
    const range = { from: '2026-11-01', to: '2026-11-30' };

    it('is true for an identical range', () => {
        expect(sameRange(range, { ...range })).toBe(true);
    });

    it('is false when either bound differs', () => {
        expect(sameRange(range, { from: '2026-11-01', to: '2026-12-06' })).toBe(false);
        expect(sameRange(range, { from: '2026-10-26', to: '2026-11-30' })).toBe(false);
    });

    it('is false when there is no previous range', () => {
        expect(sameRange(null, range)).toBe(false);
    });
});
