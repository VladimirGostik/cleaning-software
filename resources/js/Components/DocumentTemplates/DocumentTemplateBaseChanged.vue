<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DocumentTokenEditor from '@/Components/Documents/DocumentTokenEditor.vue';

defineProps<{
    previousBaseBody: string | null;
    baseBody: string;
    tokens: readonly App.Data.DocumentTemplates.DocumentTokenData[];
    processing: boolean;
    canAcknowledge: boolean;
    canReset: boolean;
}>();

const emit = defineEmits<{
    keep: [];
    restore: [];
}>();

const { t } = useI18n();

const comparing = ref(false);
</script>

<template>
    <div class="alert alert-warning alert-soft mb-6 flex-col items-stretch gap-3" role="status">
        <div>
            <p class="font-semibold">{{ t('document_template_base_changed_title') }}</p>
            <p class="text-sm">{{ t('document_template_base_changed_description') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button v-if="canAcknowledge" type="button" class="btn btn-sm" :disabled="processing" @click="emit('keep')">
                {{ t('document_template_keep_mine') }}
            </button>
            <button
                v-if="canReset"
                type="button"
                class="btn btn-sm btn-neutral"
                :disabled="processing"
                @click="emit('restore')"
            >
                {{ t('document_template_restore_base') }}
            </button>
            <button
                type="button"
                class="btn btn-ghost btn-sm"
                :aria-expanded="comparing"
                aria-controls="document-template-base-compare"
                @click="comparing = !comparing"
            >
                {{ comparing ? t('document_template_compare_hide') : t('document_template_compare_show') }}
            </button>
        </div>

        <div v-if="comparing" id="document-template-base-compare" class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <section>
                <h3 class="mb-1 text-sm font-semibold">{{ t('document_template_base_previous') }}</h3>
                <div class="h-96">
                    <DocumentTokenEditor
                        :model-value="previousBaseBody ?? ''"
                        :tokens="tokens"
                        :aria-label="t('document_template_base_previous')"
                        readonly
                    />
                </div>
            </section>
            <section>
                <h3 class="mb-1 text-sm font-semibold">{{ t('document_template_base_current') }}</h3>
                <div class="h-96">
                    <DocumentTokenEditor
                        :model-value="baseBody"
                        :tokens="tokens"
                        :aria-label="t('document_template_base_current')"
                        readonly
                    />
                </div>
            </section>
        </div>
    </div>
</template>
