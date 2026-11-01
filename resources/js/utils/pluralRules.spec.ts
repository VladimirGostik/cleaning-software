import { createI18n } from 'vue-i18n';
import { describe, expect, it } from 'vitest';
import { pluralRules } from '@/utils/pluralRules';

import skApp from '../../lang/sk/app.json';

const make = (locale: string) =>
    createI18n({ legacy: false, locale, messages: { sk: skApp, uk: skApp, en: skApp }, pluralRules });

describe('pluralRules', () => {
    it('picks sk forms for document_missing_summary', () => {
        const { t } = make('sk').global;
        const text = (n: number) => t('document_missing_summary', { count: n }, n);
        expect(text(1)).toBe('V texte chýba 1 premenná');
        expect(text(3)).toBe('V texte chýbajú 3 premenné');
        expect(text(5)).toBe('V texte chýba 5 premenných');
        expect(text(12)).toBe('V texte chýba 12 premenných');
        expect(text(22)).toBe('V texte chýba 22 premenných');
    });

    it('keeps default zero/one/other behaviour for three-form keys', () => {
        const { t } = make('sk').global;
        expect(t('invoice_stat_count', { count: 0 }, 0)).toBe('0 faktúr');
        expect(t('invoice_stat_count', { count: 1 }, 1)).toBe('1 faktúra');
        expect(t('invoice_stat_count', { count: 7 }, 7)).toBe('7 faktúr');
    });

    it('picks uk forms', () => {
        const rule = pluralRules.uk;
        expect([1, 2, 5, 11, 21, 22, 25].map((n) => rule(n, 4))).toEqual([1, 2, 3, 3, 1, 2, 3]);
    });
});
