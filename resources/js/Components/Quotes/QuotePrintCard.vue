<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import { documentTemplateScopeKey } from '@/utils/enums';

const props = defineProps<{
    templates: readonly App.Data.Quotes.QuoteTemplateOptionData[];
    templateId: string | null;
    templateName: string | null;
    isEdited: boolean;
    frozen: boolean;
    isVatPayer: boolean;
    pendingTemplateId: string | undefined;
    disabled?: boolean;
    loading?: boolean;
    error?: string | null;
    noteError?: string | null;
    includedError?: string | null;
}>();

const note = defineModel<string | null>('note', { required: true });
const includedText = defineModel<string | null>('includedText', { required: true });
const showTotals = defineModel<boolean>('showTotals', { required: true });
const showVatNote = defineModel<boolean>('showVatNote', { required: true });

const emit = defineEmits<{
    pick: [id: string];
    confirmPick: [];
    cancelPick: [];
    edit: [];
    preview: [];
}>();

const { t } = useI18n();

const options = computed<SelectOption[]>(() =>
    props.templates.map((template) => ({
        value: template.id,
        label: `${template.name} (${t(documentTemplateScopeKey(template.scope))})`,
    })),
);

const statusText = computed(() => {
    if (props.isEdited) return t('quote_text_status_edited');

    return props.templateName ? t('quote_text_from_template', { name: props.templateName }) : '';
});
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <div class="flex items-center gap-2">
                <h2 class="card-title text-base">{{ t('quote_section_print') }}</h2>
                <span v-if="frozen" class="kg-chip kg-chip--neutral">{{ t('quote_text_frozen') }}</span>
            </div>

            <div>
                <TextareaInput
                    :model-value="note ?? ''"
                    :label="t('note')"
                    :rows="2"
                    :error="noteError"
                    @update:model-value="note = $event"
                />
                <p class="mt-1 text-xs text-base-content/60">{{ t('quote_note_hint') }}</p>
            </div>

            <div>
                <TextareaInput
                    :model-value="includedText ?? ''"
                    :label="t('quote_pdf_included')"
                    :rows="4"
                    :error="includedError"
                    @update:model-value="includedText = $event"
                />
                <p class="mt-1 text-xs text-base-content/60">{{ t('quote_included_hint') }}</p>
            </div>

            <div class="space-y-2">
                <div>
                    <ToggleInput v-model="showTotals" :label="t('quote_show_totals')" />
                    <p class="text-xs text-base-content/60">{{ t('quote_show_totals_hint') }}</p>
                </div>
                <ToggleInput v-if="!isVatPayer" v-model="showVatNote" :label="t('quote_show_vat_note')" />
            </div>

            <div class="divider my-0" />

            <div class="space-y-3">
                <p v-if="statusText" class="text-sm text-base-content/70" role="status">{{ statusText }}</p>

                <SelectInput
                    v-if="templates.length > 1"
                    :model-value="templateId ?? ''"
                    :label="t('quote_text_template')"
                    :options="options"
                    :disabled="disabled || frozen"
                    @update:model-value="emit('pick', $event)"
                />
                <p v-if="error" class="text-sm text-error" role="alert">{{ error }}</p>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-neutral"
                        :disabled="disabled || frozen || loading"
                        @click="emit('edit')"
                    >
                        <span v-if="loading" class="loading loading-spinner loading-xs" />
                        {{ t('quote_text_edit') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-neutral" :disabled="disabled" @click="emit('preview')">
                        {{ t('preview') }}
                    </button>
                </div>
            </div>
        </div>

        <ConfirmDeleteModal
            :is-open="pendingTemplateId !== undefined"
            :title="t('quote_text_template')"
            :description="t('quote_text_replace_confirm')"
            :confirm-label="t('quote_text_confirm_action')"
            confirm-variant="warning"
            @cancel="emit('cancelPick')"
            @confirm="emit('confirmPick')"
        />
    </div>
</template>
