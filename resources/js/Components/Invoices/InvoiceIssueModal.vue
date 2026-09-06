<script setup lang="ts">
import { shallowRef, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';

const props = defineProps<{
    open: boolean;
    invoiceId: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

function makeForm(id: string | null) {
    return useForm('post', id ? `/invoices/${id}/issue` : '', { number: '' }).transform((data: { number: string }) => ({
        number: data.number || null,
    }));
}

const form = shallowRef(makeForm(props.invoiceId));

watch(
    () => props.invoiceId,
    (id) => {
        form.value = makeForm(id);
    },
);

function submit(): void {
    form.value.submit({ preserveScroll: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <dialog class="modal" :class="{ 'modal-open': props.open }">
        <div class="modal-box">
            <h3 class="text-lg font-bold">{{ t('invoice_action_issue') }}</h3>

            <FormProvider :form="form">
                <form novalidate class="mt-4" @submit.prevent="submit">
                    <TextInput
                        field="number"
                        :label="t('invoice_issue_number_label')"
                        :placeholder="t('invoice_issue_number_placeholder')"
                    />
                    <p class="text-sm text-base-content/60">{{ t('invoice_issue_hint') }}</p>

                    <div class="modal-action">
                        <FormActions
                            :submit-label="t('invoice_action_issue')"
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
