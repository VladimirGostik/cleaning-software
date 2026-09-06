<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import QuoteKindBadge from './QuoteKindBadge.vue';
import { formatDate } from '@/utils/date';

const props = defineProps<{
    quote: App.Data.Quotes.QuoteDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
        <div v-if="props.quote.subject">
            <dt class="text-xs text-base-content/50">{{ t('quote_subject_text') }}</dt>
            <dd>{{ props.quote.subject }}</dd>
        </div>
        <div>
            <dt class="text-xs text-base-content/50">{{ t('quote_issue_date') }}</dt>
            <dd>{{ formatDate(props.quote.issue_date) }}</dd>
        </div>
        <div>
            <dt class="text-xs text-base-content/50">{{ t('quote_valid_until') }}</dt>
            <dd>{{ formatDate(props.quote.valid_until) }}</dd>
        </div>
        <div>
            <dt class="text-xs text-base-content/50">{{ t('invoice_currency') }}</dt>
            <dd>{{ props.quote.currency }}</dd>
        </div>
        <div>
            <dt class="text-xs text-base-content/50">{{ t('quote_kind') }}</dt>
            <dd><QuoteKindBadge :kind="props.quote.kind" :has-document="props.quote.document !== null" /></dd>
        </div>
    </dl>
</template>
