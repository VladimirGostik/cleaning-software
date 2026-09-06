<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { quoteKindKey } from '@/utils/enums';

const props = withDefaults(
    defineProps<{
        kind: App.Enums.QuoteKindEnum;
        hasDocument?: boolean;
    }>(),
    { hasDocument: true },
);

const { t } = useI18n();
</script>

<template>
    <span class="badge badge-sm badge-outline">
        {{ t(quoteKindKey(props.kind)) }}
        <ExclamationTriangleIcon
            v-if="props.kind === 'document' && !props.hasDocument"
            class="size-3 text-warning"
            :title="t('quote_document_missing_short')"
        />
        <span v-if="props.kind === 'document' && !props.hasDocument" class="sr-only">
            {{ t('quote_document_missing_short') }}
        </span>
    </span>
</template>
