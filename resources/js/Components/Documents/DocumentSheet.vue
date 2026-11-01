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
    }>(),
    { tokens: () => [], letterhead: null, footer: () => [] },
);

const rendered = computed(() => storedToReadonlyHtml(props.html, props.tokens));
</script>

<template>
    <div class="document-desk h-full min-h-0">
        <article class="document-page">
            <DocumentFrame part="header" :letterhead="letterhead" outlined />

            <!-- eslint-disable-next-line vue/no-v-html -- html is sanitized by the server on save -->
            <div class="document-body" v-html="rendered" />

            <DocumentFrame part="footer" :footer="footer" outlined />
        </article>
    </div>
</template>
