<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import StatusChip from '@/Components/StatusChip.vue';
import { quoteKindKey } from '@/utils/enums';

const props = withDefaults(
    defineProps<{
        kind: App.Enums.QuoteKindEnum;
        hasDocument?: boolean;
    }>(),
    { hasDocument: true },
);

const { t } = useI18n();

const missing = computed(() => props.kind === 'document' && !props.hasDocument);
const label = computed(() =>
    missing.value
        ? `${t(quoteKindKey(props.kind))}: ${t('quote_document_missing_short')}`
        : t(quoteKindKey(props.kind)),
);
</script>

<template>
    <StatusChip :tone="missing ? 'warning' : 'neutral'" :label="label" size="sm" />
</template>
