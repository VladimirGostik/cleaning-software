export function toNumber(value: string | number | null | undefined): number {
    if (value === null || value === undefined) return 0;
    const parsed = typeof value === 'number' ? value : parseFloat(value);
    return Number.isNaN(parsed) ? 0 : parsed;
}

export function round2(n: number): number {
    return Math.round(n * 100) / 100;
}

export function roundAmount(amount: number, mode: App.Enums.RoundingModeEnum): number {
    if (mode === 'document') return Math.round(amount);
    if (mode === 'cash_005') return Math.round(amount / 0.05) * 0.05;
    return amount;
}

export function localeTag(locale: string): string {
    if (locale === 'sk') return 'sk-SK';
    if (locale === 'en') return 'en-GB';
    if (locale === 'uk') return 'uk-UA';
    return locale;
}

export function formatMoney(
    amount: string | number | null | undefined,
    currency: App.Enums.CurrencyEnum,
    locale: string,
): string {
    return new Intl.NumberFormat(localeTag(locale), {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(toNumber(amount));
}

export function formatPercent(rate: number, locale: string): string {
    return `${new Intl.NumberFormat(localeTag(locale), { maximumFractionDigits: 2 }).format(rate)} %`;
}
