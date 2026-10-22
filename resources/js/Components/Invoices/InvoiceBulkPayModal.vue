<script setup lang="ts">
import { shallowRef, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { callValidate } from '@/Components/Forms/useFieldError';
import { toDateInputValue } from '@/utils/date';

const props = defineProps<{
    open: boolean;
    ids: string[];
}>();

const emit = defineEmits<{
    close: [];
    done: [];
}>();

const { t } = useI18n();

function makeForm() {
    return useForm('post', '/invoices/bulk/pay', {
        ids: props.ids,
        paid_on: toDateInputValue(new Date()) as string | null,
    });
}

const form = shallowRef(makeForm());
const today = toDateInputValue(new Date());

watch(
    () => props.open,
    (open) => {
        if (open) form.value = makeForm();
    },
);

function update(value: string | null): void {
    form.value.paid_on = value;
    callValidate(form.value, 'paid_on');
}

function submit(): void {
    form.value.submit({
        preserveScroll: true,
        onSuccess: () => {
            emit('done');
            emit('close');
        },
    });
}
</script>

<template>
    <dialog class="modal" :class="{ 'modal-open': props.open }">
        <div class="modal-box">
            <h3 class="text-lg font-bold">{{ t('invoice_bulk_pay_title') }}</h3>
            <p class="py-2 text-base-content/70">{{ t('invoice_bulk_pay_hint') }}</p>

            <FormProvider :form="form">
                <form novalidate class="mt-2 space-y-2" @submit.prevent="submit">
                    <DateInput
                        :model-value="form.paid_on"
                        :label="t('invoice_bulk_paid_on')"
                        :max="today"
                        required
                        :error="form.errors.paid_on"
                        @update:model-value="update"
                    />
                    <p v-if="form.errors.ids" class="text-sm text-error">{{ form.errors.ids }}</p>

                    <div class="modal-action">
                        <FormActions
                            :submit-label="t('invoice_bulk_pay')"
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
