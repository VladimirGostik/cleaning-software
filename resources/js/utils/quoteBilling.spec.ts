import { describe, expect, it } from 'vitest';
import {
    includedLines,
    periodTotalsFromData,
    quoteLineAmountLabel,
    quotePeriodTotals,
    quoteQuantityLabel,
    quoteUnitPriceLabel,
    type QuoteBillingItem,
} from './quoteBilling';
import { computeLineTotals } from '@/Composables/useInvoiceTotals';

const MESSAGES: Record<string, string> = {
    quote_period_adverb_monthly: 'mesačne',
    quote_period_adverb_quarterly: 'štvrťročne',
    quote_period_adverb_yearly: 'ročne',
    quote_period_unit_monthly: 'mesiac',
    quote_period_unit_quarterly: 'štvrťrok',
    quote_period_unit_yearly: 'rok',
    quote_period_suffix_monthly: 'mes.',
    quote_period_suffix_quarterly: 'štvrťrok',
    quote_period_suffix_yearly: 'rok',
    quote_pdf_discount_inline: ', zľava :percent %',
};
const t = (key: string): string => MESSAGES[key] ?? key;
const money = (amount: number): string => `${amount.toFixed(2).replace('.', ',')} €`;

function item(overrides: Partial<QuoteBillingItem> = {}): QuoteBillingItem {
    return {
        billing_period: 'one_off',
        quantity: 1,
        unit: null,
        unit_price: 100,
        discount_percent: 0,
        vat_rate: 20,
        ...overrides,
    };
}

describe('quotePeriodTotals', () => {
    it('groups a five item mix in enum order', () => {
        const groups = quotePeriodTotals(
            [
                item({ billing_period: 'yearly', unit_price: 200 }),
                item({ billing_period: 'monthly', unit_price: 70 }),
                item({ billing_period: 'one_off', unit_price: 300 }),
                item({ billing_period: 'quarterly', unit_price: 80 }),
                item({ billing_period: 'monthly', quantity: 2, unit_price: 25 }),
            ],
            false,
        );

        expect(groups.map((g) => [g.billing_period, g.total])).toEqual([
            ['one_off', 300],
            ['monthly', 120],
            ['quarterly', 80],
            ['yearly', 200],
        ]);
    });

    it('keeps a group priced at zero', () => {
        const groups = quotePeriodTotals([item({ billing_period: 'monthly', unit_price: 0 })], false);
        expect(groups).toEqual([{ billing_period: 'monthly', subtotal: 0, vat_amount: 0, total: 0 }]);
    });

    it('returns no groups without items', () => {
        expect(quotePeriodTotals([], true)).toEqual([]);
    });

    it('sums base and vat per group for a vat payer', () => {
        const rows = [
            item({ billing_period: 'monthly', unit_price: 33.33, vat_rate: 20 }),
            item({ billing_period: 'monthly', unit_price: 10.05, vat_rate: 10 }),
        ];
        const [group] = quotePeriodTotals(rows, true);
        const lines = rows.map((row) => computeLineTotals(row, true));

        expect(group.subtotal).toBeCloseTo(lines[0].base + lines[1].base, 2);
        expect(group.vat_amount).toBeCloseTo(lines[0].vat + lines[1].vat, 2);
        expect(group.total).toBeCloseTo(group.subtotal + group.vat_amount, 2);
    });

    it('applies the discount', () => {
        const [group] = quotePeriodTotals([item({ unit_price: 200, discount_percent: 10 })], false);
        expect(group.total).toBe(180);
    });
});

describe('periodTotalsFromData', () => {
    it('parses decimal strings', () => {
        expect(
            periodTotalsFromData([
                { billing_period: 'monthly', subtotal: '100.00', vat_amount: '20.00', total: '120.00' },
            ]),
        ).toEqual([{ billing_period: 'monthly', subtotal: 100, vat_amount: 20, total: 120 }]);
    });
});

describe('display helpers', () => {
    it('labels a single monthly item', () => {
        const row = item({ billing_period: 'monthly', unit_price: 70 });
        expect(quoteQuantityLabel(row, t, 'sk')).toBe('mesačne');
        expect(quoteUnitPriceLabel(row, 'EUR', t, money, 'sk')).toBe('70,00 € / mesiac');
        expect(quoteLineAmountLabel(row, false, 'EUR', t, money)).toBe('70,00 € / mes.');
    });

    it('labels a multiple monthly item with a unit', () => {
        const row = item({ billing_period: 'monthly', quantity: 2, unit: 'upratovanie', unit_price: 25 });
        expect(quoteQuantityLabel(row, t, 'sk')).toBe('2× mesačne');
        expect(quoteUnitPriceLabel(row, 'EUR', t, money, 'sk')).toBe('25,00 € / upratovanie');
        expect(quoteLineAmountLabel(row, false, 'EUR', t, money)).toBe('50,00 € / mes.');
    });

    it('labels quarterly and yearly items', () => {
        expect(quoteQuantityLabel(item({ billing_period: 'quarterly' }), t, 'sk')).toBe('štvrťročne');
        expect(quoteLineAmountLabel(item({ billing_period: 'quarterly' }), false, 'EUR', t, money)).toBe(
            '100,00 € / štvrťrok',
        );
        expect(quoteQuantityLabel(item({ billing_period: 'yearly', quantity: 2 }), t, 'sk')).toBe('2× ročne');
    });

    it('labels a one-off item without a suffix', () => {
        const row = item({ unit_price: 300 });
        expect(quoteQuantityLabel(row, t, 'sk')).toBe('1×');
        expect(quoteUnitPriceLabel(row, 'EUR', t, money, 'sk')).toBe('300,00 €');
        expect(quoteLineAmountLabel(row, false, 'EUR', t, money)).toBe('300,00 €');
    });

    it('shows the discount inside the unit price', () => {
        expect(quoteUnitPriceLabel(item({ unit_price: 100, discount_percent: 10 }), 'EUR', t, money, 'sk')).toBe(
            '100,00 €, zľava 10 %',
        );
    });

    it('keeps the unit on a one-off item', () => {
        expect(quoteUnitPriceLabel(item({ unit: 'm²', unit_price: 5 }), 'EUR', t, money, 'sk')).toBe('5,00 € / m²');
    });

    it('prefers the unit over the period noun for a recurring single item', () => {
        const row = item({ billing_period: 'monthly', unit: 'upratovanie', unit_price: 70 });
        expect(quoteUnitPriceLabel(row, 'EUR', t, money, 'sk')).toBe('70,00 € / upratovanie');
    });

    it('uses the period noun for a recurring single item without a unit', () => {
        expect(quoteUnitPriceLabel(item({ billing_period: 'monthly' }), 'EUR', t, money, 'sk')).toBe(
            '100,00 € / mesiac',
        );
    });

    it('prints no unit for a recurring multiple item without a unit', () => {
        const row = item({ billing_period: 'monthly', quantity: 2 });
        expect(quoteUnitPriceLabel(row, 'EUR', t, money, 'sk')).toBe('100,00 €');
    });

    it('formats fractional quantity and discount with a decimal comma', () => {
        expect(quoteQuantityLabel(item({ quantity: 2.5 }), t, 'sk')).toBe('2,5×');
        expect(quoteUnitPriceLabel(item({ discount_percent: 10.5 }), 'EUR', t, money, 'sk')).toBe(
            '100,00 €, zľava 10,5 %',
        );
    });

    it('uses the base amount for a vat payer', () => {
        expect(quoteLineAmountLabel(item({ unit_price: 100 }), true, 'EUR', t, money)).toBe('100,00 €');
        expect(quoteLineAmountLabel(item({ unit_price: 100 }), false, 'EUR', t, money)).toBe('100,00 €');
        expect(quoteLineAmountLabel(item({ line_base: 80, line_total: 96 }), true, 'EUR', t, money)).toBe('80,00 €');
    });
});

describe('includedLines', () => {
    it('trims and drops blank lines', () => {
        expect(includedLines('  a \n\n b\r\n   \nc')).toEqual(['a', 'b', 'c']);
    });

    it('handles null', () => {
        expect(includedLines(null)).toEqual([]);
    });
});
