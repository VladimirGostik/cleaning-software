import { describe, expect, it } from 'vitest';
import { normalizeSearchText } from './text';

describe('normalizeSearchText', () => {
    it('strips diacritics, lowercases and trims', () => {
        expect(normalizeSearchText('  Názov Šablóny ČŠ ')).toBe('nazov sablony cs');
    });
});
