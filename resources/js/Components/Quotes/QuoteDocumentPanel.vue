<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthorization } from '@/Composables/useAuthorization';
import { formatBytes } from '@/utils/bytes';

const props = defineProps<{
    quote: App.Data.Quotes.QuoteDetailData;
}>();

const { t } = useI18n();
const { allows } = useAuthorization();

const canEdit = computed(() => allows('edit quotes') && props.quote.status === 'draft');
</script>

<template>
    <div>
        <div
            v-if="props.quote.document"
            class="flex items-center justify-between gap-3 rounded-box border border-base-300 p-3"
        >
            <div class="min-w-0">
                <p class="truncate font-medium">{{ props.quote.document.file_name }}</p>
                <p class="text-xs text-base-content/60">
                    {{ props.quote.document.mime_type ?? t('empty_dash') }} ·
                    {{ formatBytes(props.quote.document.size) }}
                </p>
                <a v-if="canEdit" :href="`/quotes/${props.quote.id}/edit`" class="link link-hover text-xs">
                    {{ t('quote_document_replace_hint') }}
                </a>
            </div>
            <a :href="props.quote.document.download_url" target="_blank" rel="noopener" class="btn btn-sm shrink-0">
                {{ t('quote_document_download') }}
            </a>
        </div>

        <div v-else class="alert alert-warning">
            <div>
                <h3 class="font-semibold">{{ t('quote_document_missing_title') }}</h3>
                <p class="text-sm">{{ t('quote_document_missing_hint') }}</p>
                <a v-if="canEdit" :href="`/quotes/${props.quote.id}/edit`" class="link link-hover text-sm font-medium">
                    {{ t('edit') }}
                </a>
            </div>
        </div>
    </div>
</template>
