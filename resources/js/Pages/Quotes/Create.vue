<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import QuoteForm from '@/Components/Quotes/QuoteForm.vue';

import type { Breadcrumb } from '@/types';

defineProps<{
    context: App.Data.Quotes.QuoteFormContextData;
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('quotes'), url: '/quotes' },
    { label: t('quote_add') },
];
</script>

<template>
    <AppLayout>
        <Header :title="t('quote_add')" :breadcrumbs="breadcrumbs" />

        <QuoteForm :context="context" />
    </AppLayout>
</template>
