<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import QuoteKindBadge from './QuoteKindBadge.vue';
import QuoteTotalsSplit from './QuoteTotalsSplit.vue';
import { formatDate } from '@/utils/date';
import type { QuotePeriodTotals } from '@/utils/quoteBilling';

const props = defineProps<{
    number: string | null;
    kind: App.Enums.QuoteKindEnum;
    issueDate: string;
    validUntil: string;
    currency: App.Enums.CurrencyEnum;
    isVatPayer: boolean;
    periodTotals: readonly QuotePeriodTotals[];
    showTotals: boolean;
    previewLoading: boolean;
}>();

const emit = defineEmits<{
    preview: [];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('quote_section_summary') }}</h2>

            <div class="flex items-center justify-between">
                <span class="tabular-nums font-medium">{{ props.number ?? t('quote_no_number') }}</span>
                <QuoteKindBadge :kind="props.kind" />
            </div>

            <div class="grid grid-cols-2 gap-2 text-sm text-base-content/70">
                <div>
                    <p class="text-xs text-base-content/50">{{ t('quote_issue_date') }}</p>
                    <p>{{ formatDate(props.issueDate) }}</p>
                </div>
                <div>
                    <p class="text-xs text-base-content/50">{{ t('quote_valid_until') }}</p>
                    <p>{{ formatDate(props.validUntil) }}</p>
                </div>
            </div>

            <div class="divider my-0" />

            <QuoteTotalsSplit
                v-if="props.kind === 'itemized'"
                :period-totals="props.periodTotals"
                :currency="props.currency"
                :is-vat-payer="props.isVatPayer"
            />
            <p v-if="props.kind === 'itemized' && !props.showTotals" class="text-xs text-base-content/60">
                {{ t('quote_totals_not_printed') }}
            </p>

            <button
                v-if="props.kind === 'itemized'"
                type="button"
                class="btn btn-sm btn-neutral w-full"
                :disabled="props.previewLoading"
                @click="emit('preview')"
            >
                <span v-if="props.previewLoading" class="loading loading-spinner loading-xs" />
                {{ t('preview') }}
            </button>
        </div>
    </div>
</template>
