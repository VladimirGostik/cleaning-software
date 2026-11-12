<script setup lang="ts">
import { computed } from 'vue';
import DocumentFrame from '@/Components/Documents/DocumentFrame.vue';
import { storedToReadonlyHtml } from '@/Components/Documents/editor/extensions/templateTokenHtml';

const props = withDefaults(
    defineProps<{
        html: string;
        tokens?: readonly App.Data.DocumentTemplates.DocumentTokenData[];
        letterhead?: App.Data.Documents.DocumentLetterheadData | null;
        footer?: string[];
        variant?: 'letterhead' | 'kegos';
    }>(),
    { tokens: () => [], letterhead: null, footer: () => [], variant: 'letterhead' },
);

const rendered = computed(() => storedToReadonlyHtml(props.html, props.tokens));
</script>

<template>
    <div class="document-desk h-full min-h-0">
        <article class="document-page" :class="{ 'document-page--quote flex flex-col': variant === 'kegos' }">
            <DocumentFrame part="header" :letterhead="letterhead" :variant="variant" outlined />

            <!-- eslint-disable-next-line vue/no-v-html -- html is sanitized by the server on save -->
            <div class="document-body" :class="{ 'document-body--quote': variant === 'kegos' }" v-html="rendered" />

            <DocumentFrame part="footer" :footer="footer" :variant="variant" outlined />
        </article>
    </div>
</template>
