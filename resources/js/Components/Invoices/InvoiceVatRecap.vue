<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';

const props = defineProps<{
    breakdown: readonly { rate: number; base: number; vat: number; total: number }[];
    currency: App.Enums.CurrencyEnum;
}>();

const { t } = useI18n();
const { money, percent } = useMoneyFormat();
</script>

<template>
    <table v-if="props.breakdown.length > 0" class="table table-xs">
        <caption class="text-left text-sm font-medium pb-1">
            {{
                t('invoice_vat_recap_title')
            }}
        </caption>
        <thead>
            <tr>
                <th>{{ t('invoice_vat_recap_rate') }}</th>
                <th class="text-right">{{ t('invoice_vat_recap_base') }}</th>
                <th class="text-right">{{ t('invoice_vat_recap_vat') }}</th>
                <th class="text-right">{{ t('invoice_vat_recap_total') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="line in props.breakdown" :key="line.rate">
                <td>{{ percent(line.rate) }}</td>
                <td class="text-right">{{ money(line.base, props.currency) }}</td>
                <td class="text-right">{{ money(line.vat, props.currency) }}</td>
                <td class="text-right">{{ money(line.total, props.currency) }}</td>
            </tr>
        </tbody>
    </table>
</template>
