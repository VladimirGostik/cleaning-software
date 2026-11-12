<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { isOneOffOnly, isRecurringPeriod, type QuotePeriodTotals } from '@/utils/quoteBilling';
import { quoteBillingPeriodKey, quoteBillingSuffixKey } from '@/utils/enums';

const props = defineProps<{
    periodTotals: readonly QuotePeriodTotals[];
    currency: App.Enums.CurrencyEnum;
    isVatPayer: boolean;
}>();

const { t } = useI18n();
const { money } = useMoneyFormat();

const oneOffOnly = computed(() => isOneOffOnly(props.periodTotals));

function groupLabel(group: QuotePeriodTotals): string {
    return oneOffOnly.value ? t('quote_total') : t(quoteBillingPeriodKey(group.billing_period));
}

function totalLabel(group: QuotePeriodTotals): string {
    const amount = money(group.total, props.currency);
    return isRecurringPeriod(group.billing_period)
        ? `${amount} / ${t(quoteBillingSuffixKey(group.billing_period))}`
        : amount;
}
</script>

<template>
    <p v-if="props.periodTotals.length === 0" class="text-sm text-muted">{{ t('quote_totals_empty') }}</p>

    <dl v-else class="space-y-3 text-sm">
        <div v-for="group in props.periodTotals" :key="group.billing_period" class="space-y-1">
            <template v-if="props.isVatPayer">
                <div class="flex justify-between">
                    <dt class="text-muted">{{ t('invoice_pdf_subtotal') }}</dt>
                    <dd class="tabular-nums">{{ money(group.subtotal, props.currency) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">{{ t('quote_pdf_vat_rate') }}</dt>
                    <dd class="tabular-nums">{{ money(group.vat_amount, props.currency) }}</dd>
                </div>
                <div class="flex justify-between pt-2 text-lg font-medium">
                    <dt>{{ groupLabel(group) }}</dt>
                    <dd class="tabular-nums">{{ totalLabel(group) }}</dd>
                </div>
            </template>
            <div v-else class="flex justify-between text-lg font-medium">
                <dt>{{ groupLabel(group) }}</dt>
                <dd class="tabular-nums">{{ totalLabel(group) }}</dd>
            </div>
        </div>

        <p v-if="!props.isVatPayer" class="text-xs text-muted">
            {{ t('quote_pdf_non_vat_clause') }}
        </p>
    </dl>
</template>
