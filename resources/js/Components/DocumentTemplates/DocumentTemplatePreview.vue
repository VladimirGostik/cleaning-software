<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import DocumentSheet from '@/Components/Documents/DocumentSheet.vue';

const props = withDefaults(
    defineProps<{
        preview: App.Data.Documents.DocumentRenderData | null;
        loading: boolean;
        error: string | null;
        tokens: readonly App.Data.DocumentTemplates.DocumentTokenData[];
        variant?: 'letterhead' | 'kegos';
    }>(),
    { variant: 'letterhead' },
);

const { t } = useI18n();

const unknownTokens = computed(() => props.preview?.unknown_tokens ?? []);
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-3">
        <div class="flex shrink-0 items-center gap-3">
            <p class="text-xs text-base-content/60">{{ t('document_template_preview_sample_hint') }}</p>
            <span v-if="loading" class="loading loading-spinner loading-xs" role="status" :aria-label="t('loading')" />
        </div>

        <div v-if="unknownTokens.length > 0" class="alert alert-warning alert-soft shrink-0 flex-col items-stretch">
            <p class="text-sm font-semibold">{{ t('document_template_preview_unknown_title') }}</p>
            <ul class="list-disc pl-5 text-sm">
                <li v-for="token in unknownTokens" :key="token">{{ token }}</li>
            </ul>
        </div>

        <p v-if="error" class="shrink-0 text-sm text-error" role="alert">{{ t('document_template_preview_failed') }}</p>

        <div v-if="preview" class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300">
            <DocumentSheet
                :html="preview.html"
                :tokens="tokens"
                :letterhead="preview.letterhead"
                :footer="preview.footer"
                :variant="variant"
            />
        </div>
        <p v-else-if="!loading && !error" class="shrink-0 text-sm text-base-content/60">
            {{ t('document_template_preview_empty') }}
        </p>
    </div>
</template>
