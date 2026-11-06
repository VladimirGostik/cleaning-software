import { describe, expect, it } from 'vitest';
import { initials, normalizeSearchText } from './text';

describe('normalizeSearchText', () => {
    it('strips diacritics, lowercases and trims', () => {
        expect(normalizeSearchText('  Názov Šablóny ČŠ ')).toBe('nazov sablony cs');
    });
});

describe('initials', () => {
    it('takes the first letters of the first two words', () => {
        expect(initials('Ján Novák')).toBe('JN');
        expect(initials('Anna Maria Kováč')).toBe('AM');
    });

    it('handles a single word, extra spaces and blank input', () => {
        expect(initials('Mária')).toBe('M');
        expect(initials('  ján   novák  ')).toBe('JN');
        expect(initials('')).toBe('');
        expect(initials('   ')).toBe('');
    });
});
