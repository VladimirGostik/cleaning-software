<script setup lang="ts">
import { shallowRef, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import CheckboxInput from '@/Components/Forms/CheckboxInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';

const props = defineProps<{
    open: boolean;
    invoiceId: string;
    options: App.Data.Invoices.InvoiceSendOptionsData;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

interface SendFormData {
    recipient_email: string;
    save_for_future: boolean;
}

function makeForm(id: string) {
    return useForm('post', `/invoices/${id}/send`, {
        recipient_email: '',
        save_for_future: false,
    } satisfies SendFormData).transform((data: SendFormData) => ({
        ...data,
        recipient_email: data.recipient_email || null,
    }));
}

const form = shallowRef(makeForm(props.invoiceId));

watch(
    () => [props.invoiceId, props.open] as const,
    ([id, open]) => {
        if (open) form.value = makeForm(id);
    },
);

function submit(): void {
    form.value.submit({ preserveScroll: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <dialog class="modal" :class="{ 'modal-open': props.open }">
        <div class="modal-box">
            <h3 class="text-lg font-medium">{{ t('invoice_action_send_email') }}</h3>

            <FormProvider :form="form">
                <form novalidate class="mt-4 space-y-2" @submit.prevent="submit">
                    <div>
                        <TextInput
                            field="recipient_email"
                            type="email"
                            :label="t('invoice_send_recipient')"
                            :suggestions="options.email_options"
                            required
                        />
                        <p class="text-xs text-muted">{{ t('invoice_send_recipient_hint') }}</p>
                    </div>

                    <CheckboxInput
                        v-if="options.save_target !== null"
                        field="save_for_future"
                        :label="
                            t(
                                options.save_target === 'recurring_invoice'
                                    ? 'invoice_send_save_to_recurring'
                                    : 'invoice_send_save_to_object',
                            )
                        "
                    />

                    <div class="modal-action">
                        <FormActions
                            :submit-label="t('invoice_action_send_email')"
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
