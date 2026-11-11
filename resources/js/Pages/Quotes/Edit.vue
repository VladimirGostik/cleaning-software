<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import QuoteForm from '@/Components/Quotes/QuoteForm.vue';

import type { Breadcrumb } from '@/types';

const props = defineProps<{
    quote: App.Data.Quotes.QuoteDetailData;
    context: App.Data.Quotes.QuoteFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('quotes'), url: '/quotes' },
    { label: props.quote.number ?? t('quote_no_number'), url: `/quotes/${props.quote.id}` },
    { label: t('quote_edit') },
]);
</script>

<template>
    <Header :title="t('quote_edit')" :breadcrumbs="breadcrumbs" />

    <div v-if="!quote.is_editable" class="alert alert-warning">
        <span>{{ t('quote_not_editable') }}</span>
    </div>

    <template v-else>
        <div v-if="quote.status === 'sent'" class="alert alert-warning mb-4" role="status">
            <span>{{ t('quote_edit_sent_warning') }}</span>
        </div>
        <QuoteForm :context="context" :quote="quote" />
    </template>
</template>
