<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { includedLines } from '@/utils/quoteBilling';

const props = defineProps<{ text: string | null }>();

const { t } = useI18n();
const lines = computed(() => includedLines(props.text));
</script>

<template>
    <div v-if="lines.length > 0">
        <h3 class="mb-1 text-sm font-medium">{{ t('quote_pdf_included') }}</h3>
        <ul class="list-disc space-y-0.5 pl-5 text-sm text-muted">
            <li v-for="(line, index) in lines" :key="index">{{ line }}</li>
        </ul>
    </div>
</template>
