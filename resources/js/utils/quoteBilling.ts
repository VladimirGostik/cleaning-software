import { computeLineTotals, type TotalsItem } from '@/Composables/useInvoiceTotals';
import {
    QUOTE_BILLING_PERIODS,
    quoteBillingAdverbKey,
    quoteBillingSuffixKey,
    quoteBillingUnitKey,
} from '@/utils/enums';
import { formatQuantity, round2, toNumber } from '@/utils/money';

type Period = App.Enums.QuoteBillingPeriodEnum;
type Translate = (key: string) => string;
type Money = (amount: number, currency: App.Enums.CurrencyEnum) => string;

export interface QuotePeriodTotals {
    billing_period: Period;
    subtotal: number;
    vat_amount: number;
    total: number;
}

export interface QuoteBillingItem extends TotalsItem {
    billing_period: Period;
    unit: string | null;
    line_base?: number | null;
    line_total?: number | null;
}

export function isRecurringPeriod(period: Period): boolean {
    return period !== 'one_off';
}

/** One-off items only: nothing to split by period, the total reads "Spolu" (same rule as QuoteDisplay::isOneOffOnly). */
export function isOneOffOnly(groups: readonly Pick<QuotePeriodTotals, 'billing_period'>[]): boolean {
    return groups.length === 1 && groups[0].billing_period === 'one_off';
}

export function quotePeriodTotals(items: readonly QuoteBillingItem[], isVatPayer: boolean): QuotePeriodTotals[] {
    return QUOTE_BILLING_PERIODS.flatMap((period) => {
        const lines = items
            .filter((item) => item.billing_period === period)
            .map((item) => computeLineTotals(item, isVatPayer));
        if (lines.length === 0) return [];

        const subtotal = round2(lines.reduce((sum, line) => sum + line.base, 0));
        const vatAmount = round2(lines.reduce((sum, line) => sum + line.vat, 0));
        return [{ billing_period: period, subtotal, vat_amount: vatAmount, total: round2(subtotal + vatAmount) }];
    });
}

export function periodTotalsFromData(data: readonly App.Data.Quotes.QuotePeriodTotalsData[]): QuotePeriodTotals[] {
    return data.map((group) => ({
        billing_period: group.billing_period,
        subtotal: toNumber(group.subtotal),
        vat_amount: toNumber(group.vat_amount),
        total: toNumber(group.total),
    }));
}

export function quoteQuantityLabel(
    item: Pick<QuoteBillingItem, 'billing_period' | 'quantity'>,
    t: Translate,
    locale: string,
): string {
    const quantity = formatQuantity(item.quantity, locale);
    if (!isRecurringPeriod(item.billing_period)) return `${quantity}×`;

    const adverb = t(quoteBillingAdverbKey(item.billing_period));
    return item.quantity === 1 ? adverb : `${quantity}× ${adverb}`;
}

function isPeriodWordOrEmpty(item: Pick<QuoteBillingItem, 'billing_period'>, unit: string, t: Translate): boolean {
    if (unit === '') return true;

    const words = [t(quoteBillingUnitKey(item.billing_period)), t(quoteBillingSuffixKey(item.billing_period))];

    return words.some((word) => word.toLowerCase() === unit.toLowerCase());
}

export function quoteUnitPriceLabel(
    item: Pick<QuoteBillingItem, 'billing_period' | 'quantity' | 'unit' | 'unit_price' | 'discount_percent'>,
    currency: App.Enums.CurrencyEnum,
    t: Translate,
    money: Money,
    locale: string,
): string {
    let label = money(item.unit_price, currency);
    const unit = (item.unit ?? '').trim();

    if (isRecurringPeriod(item.billing_period) && toNumber(item.quantity) === 1 && isPeriodWordOrEmpty(item, unit, t)) {
        label += ` / ${t(quoteBillingSuffixKey(item.billing_period))}`;
    } else if (unit !== '') {
        label += ` / ${unit}`;
    }

    if (item.discount_percent > 0) {
        label += t('quote_pdf_discount_inline').replace(':percent', formatQuantity(item.discount_percent, locale));
    }

    return label;
}

export function quoteLineAmountLabel(
    item: QuoteBillingItem,
    isVatPayer: boolean,
    currency: App.Enums.CurrencyEnum,
    t: Translate,
    money: Money,
): string {
    const computed = computeLineTotals(item, isVatPayer);
    const amount = isVatPayer ? (item.line_base ?? computed.base) : (item.line_total ?? computed.total);
    const label = money(amount, currency);

    return isRecurringPeriod(item.billing_period)
        ? `${label} / ${t(quoteBillingSuffixKey(item.billing_period))}`
        : label;
}

export function includedLines(text: string | null | undefined): string[] {
    if (!text) return [];
    return text
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line !== '');
}
