<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useMoneyFormat } from '@/Composables/useMoneyFormat';
import { taskFrequencyKey } from '@/utils/enums';
import { quoteLineAmountLabel, quoteQuantityLabel, quoteUnitPriceLabel } from '@/utils/quoteBilling';

const props = defineProps<{
    items: readonly App.Data.Quotes.QuoteItemData[];
    currency: App.Enums.CurrencyEnum;
    isVatPayer: boolean;
}>();

const { t, locale } = useI18n();
const { money } = useMoneyFormat();
</script>

<template>
    <div class="-mx-3 overflow-x-auto px-3">
        <table class="table w-full [&_td]:px-2 [&_th]:px-2">
            <thead>
                <tr>
                    <th>{{ t('quote_pdf_service') }}</th>
                    <th class="text-right whitespace-nowrap">{{ t('quote_pdf_item_quantity') }}</th>
                    <th class="text-right whitespace-nowrap">{{ t('quote_pdf_unit_price') }}</th>
                    <th class="text-right whitespace-nowrap">{{ t('quote_pdf_line_total') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, index) in props.items" :key="index">
                    <td class="min-w-32">
                        <span class="font-medium">{{ item.description }}</span>
                        <p v-if="item.note" class="whitespace-pre-line text-xs text-muted">
                            {{ item.note }}
                        </p>
                        <p v-if="item.frequency" class="text-xs text-muted">
                            {{ t(taskFrequencyKey(item.frequency)) }}
                        </p>
                    </td>
                    <td class="text-right tabular-nums whitespace-nowrap">{{ quoteQuantityLabel(item, t, locale) }}</td>
                    <td class="text-right tabular-nums whitespace-nowrap">
                        {{ quoteUnitPriceLabel(item, props.currency, t, money, locale) }}
                    </td>
                    <td class="text-right font-medium tabular-nums whitespace-nowrap">
                        {{ quoteLineAmountLabel(item, props.isVatPayer, props.currency, t, money) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
