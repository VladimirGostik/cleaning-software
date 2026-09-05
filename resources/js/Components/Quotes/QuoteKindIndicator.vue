<script setup lang="ts">
    import { PaperClipIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';

    defineProps<{
        kind: App.Enums.QuoteKindEnum;
        hasDocument: boolean;
    }>();

    const { t } = useTranslate();
</script>

<template>
    <template v-if="kind === 'document'">
        <PaperClipIcon
            v-if="hasDocument"
            class="w-4 h-4 text-base-content/40 inline-block align-text-bottom"
            :title="t('quotes.list.has_document')"
        />
        <ExclamationTriangleIcon
            v-else
            class="w-4 h-4 text-warning inline-block align-text-bottom"
            :title="t('quotes.list.document_missing')"
        />
        <span class="sr-only">{{
            hasDocument ? t('quotes.list.has_document') : t('quotes.list.document_missing')
        }}</span>
    </template>
</template>
