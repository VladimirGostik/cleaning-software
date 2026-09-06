import { useI18n } from 'vue-i18n';
import { formatMoney, formatPercent } from '@/utils/money';

export function useMoneyFormat(): {
    money: (amount: string | number | null | undefined, currency: App.Enums.CurrencyEnum) => string;
    percent: (rate: number) => string;
} {
    const { locale } = useI18n();

    function money(amount: string | number | null | undefined, currency: App.Enums.CurrencyEnum): string {
        return formatMoney(amount, currency, locale.value);
    }

    function percent(rate: number): string {
        return formatPercent(rate, locale.value);
    }

    return { money, percent };
}
