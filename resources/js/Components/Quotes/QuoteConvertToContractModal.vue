<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';

const props = defineProps<{
    open: boolean;
    quoteId: string;
    templates: readonly App.Data.DocumentTemplates.DocumentTemplateOptionData[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const form = useForm('post', `/quotes/${props.quoteId}/convert-to-contract`, {
    document_template_id: '',
}).transform((data: { document_template_id: string }) => ({
    document_template_id: data.document_template_id || null,
}));

const templateOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('contract_no_template') },
    ...props.templates
        .filter((tpl) => tpl.type === 'service_agreement')
        .map((tpl) => ({ value: tpl.id, label: tpl.name })),
]);

function submit(): void {
    form.submit({ preserveScroll: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <dialog class="modal" :class="{ 'modal-open': props.open }">
        <div class="modal-box">
            <h3 class="text-lg font-medium">{{ t('quote_convert_contract_title') }}</h3>
            <p class="py-2 text-sm text-muted">{{ t('quote_convert_contract_hint') }}</p>

            <FormProvider :form="form">
                <form novalidate class="mt-4" @submit.prevent="submit">
                    <SelectInput
                        field="document_template_id"
                        :label="t('quote_convert_contract_template')"
                        :options="templateOptions"
                    />

                    <div class="modal-action">
                        <FormActions
                            :submit-label="t('quote_action_convert_contract')"
                            :processing="form.processing"
                            @cancel="emit('close')"
                        />
                    </div>
                </form>
            </FormProvider>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="emit('close')">close</button>
        </form>
    </dialog>
</template>
