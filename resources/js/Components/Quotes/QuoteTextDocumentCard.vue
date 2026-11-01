<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import DocumentFrameToggles from '@/Components/Documents/DocumentFrameToggles.vue';
import { documentTemplateScopeKey } from '@/utils/enums';

const props = defineProps<{
    templates: readonly App.Data.Quotes.QuoteTemplateOptionData[];
    templateId: string | null;
    hasBody: boolean;
    isEdited: boolean;
    frozen: boolean;
    pendingTemplateId: string | null | undefined;
    disabled?: boolean;
    error?: string | null;
}>();

const showHeader = defineModel<boolean>('showHeader', { required: true });
const showFooter = defineModel<boolean>('showFooter', { required: true });

const emit = defineEmits<{
    pick: [id: string | null];
    edit: [];
    confirmPick: [];
    cancelPick: [];
}>();

const { t } = useI18n();

const options = computed<SelectOption[]>(() => [
    { value: '', label: t('quote_text_no_template') },
    ...props.templates.map((template) => ({
        value: template.id,
        label: `${template.name} (${t(documentTemplateScopeKey(template.scope))})`,
    })),
]);

const statusText = computed(() => {
    if (!props.hasBody) return t('quote_text_status_none');
    return props.isEdited ? t('quote_text_status_edited') : t('quote_text_status_template');
});

const confirmDescription = computed(() =>
    props.pendingTemplateId === null ? t('quote_text_remove_confirm') : t('quote_text_replace_confirm'),
);
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <div class="flex items-center gap-2">
                <h2 class="card-title text-base">{{ t('quote_section_text') }}</h2>
                <span v-if="frozen" class="kg-chip kg-chip--neutral">{{ t('quote_text_frozen') }}</span>
            </div>

            <p class="text-sm text-base-content/70">{{ t('quote_text_hint') }}</p>

            <SelectInput
                :model-value="templateId ?? ''"
                :label="t('quote_text_template')"
                :options="options"
                :disabled="disabled || frozen"
                :error="error"
                @update:model-value="emit('pick', $event || null)"
            />

            <div v-if="hasBody" class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-base-content/70" role="status">{{ statusText }}</p>
                <DocumentFrameToggles v-model:show-header="showHeader" v-model:show-footer="showFooter" />
            </div>

            <div v-if="hasBody">
                <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    :disabled="disabled || frozen"
                    @click="emit('edit')"
                >
                    {{ t('quote_text_edit') }}
                </button>
            </div>
        </div>

        <ConfirmDeleteModal
            :is-open="pendingTemplateId !== undefined"
            :title="t('quote_text_template')"
            :description="confirmDescription"
            :confirm-label="t('quote_text_confirm_action')"
            confirm-variant="warning"
            @cancel="emit('cancelPick')"
            @confirm="emit('confirmPick')"
        />
    </div>
</template>
