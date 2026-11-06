import { describe, expect, it } from 'vitest';
import { countryName } from './country';

const messages: Record<string, string> = { country_sk: 'Slovensko' };
const i18n = { t: (key: string) => messages[key], te: (key: string) => key in messages };

describe('countryName', () => {
    it('translates a known code in any case', () => {
        expect(countryName('SK', i18n)).toBe('Slovensko');
        expect(countryName('sk', i18n)).toBe('Slovensko');
    });

    it('falls back to the raw code', () => {
        expect(countryName('AT', i18n)).toBe('AT');
    });
});
