<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { isRecurringPeriod, periodTotalsFromData } from '@/utils/quoteBilling';
import { quoteBillingSuffixKey } from '@/utils/enums';

const props = defineProps<{
    periodTotals: readonly App.Data.Quotes.QuotePeriodTotalsData[];
    fallbackTotal: string;
    currency: App.Enums.CurrencyEnum;
}>();

const { t } = useI18n();
const { money } = useMoneyFormat();

const text = computed(() => {
    const groups = periodTotalsFromData(props.periodTotals);
    if (groups.length === 0) return money(props.fallbackTotal, props.currency);

    return groups
        .map((group) => {
            const amount = money(group.total, props.currency);
            return isRecurringPeriod(group.billing_period)
                ? `${amount} / ${t(quoteBillingSuffixKey(group.billing_period))}`
                : amount;
        })
        .join(' + ');
});
</script>

<template>
    <span>{{ text }}</span>
</template>
