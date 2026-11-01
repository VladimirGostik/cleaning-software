<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import DocumentMissingTokens from '@/Components/Documents/DocumentMissingTokens.vue';
import DocumentSheet from '@/Components/Documents/DocumentSheet.vue';

const props = defineProps<{
    textDocument: App.Data.Quotes.QuoteTextDocumentData;
}>();

const { t } = useI18n();

const missing = computed(() => {
    const labels = new Map(props.textDocument.tokens.map((token) => [token.key, token.label]));

    return props.textDocument.missing_tokens.map((key) => ({ key, label: labels.get(key) ?? key }));
});

const summary = computed(() => t('document_missing_summary', { count: missing.value.length }, missing.value.length));
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="card-title text-base">{{ t('quote_section_text') }}</h2>
                <span v-if="textDocument.document_template_name" class="badge badge-ghost">
                    {{ textDocument.document_template_name }}
                </span>
                <span v-if="textDocument.is_frozen" class="badge badge-neutral">{{ t('quote_text_frozen') }}</span>
            </div>

            <DocumentMissingTokens :tokens="missing" :summary="summary" />

            <DocumentSheet
                :html="textDocument.body"
                :tokens="textDocument.tokens"
                :letterhead="textDocument.show_header ? textDocument.letterhead : null"
                :footer="textDocument.show_footer ? textDocument.footer : []"
            />
        </div>
    </div>
</template>
