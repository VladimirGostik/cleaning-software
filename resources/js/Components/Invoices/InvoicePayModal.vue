<script setup lang="ts">
import { shallowRef, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import NumberInput from '@/Components/Forms/NumberInput.vue';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { callValidate } from '@/Components/Forms/useFieldError';

import { enumOptions, PAYMENT_TYPES, paymentTypeKey } from '@/utils/enums';
import { toDateInputValue } from '@/utils/date';
import { toNumber } from '@/utils/money';

const props = defineProps<{
    open: boolean;
    invoiceId: string | null;
    balanceDue: string | null;
    paymentType: App.Enums.PaymentTypeEnum;
    creditNote?: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

interface PayFormData {
    paid_on: string | null;
    amount: number | null;
    payment_type: App.Enums.PaymentTypeEnum;
    note: string;
}

function makeForm(id: string | null) {
    return useForm('post', id ? `/invoices/${id}/pay` : '', {
        paid_on: toDateInputValue(new Date()) as string | null,
        amount: props.balanceDue === null ? null : Math.abs(toNumber(props.balanceDue)),
        payment_type: props.paymentType,
        note: '',
    } satisfies PayFormData).transform((data: PayFormData) => ({
        ...data,
        note: data.note || null,
    }));
}

const form = shallowRef(makeForm(props.invoiceId));

watch(
    () => [props.invoiceId, props.balanceDue, props.open] as const,
    ([id, , open]) => {
        if (open) {
            form.value = makeForm(id);
        }
    },
);

const paymentTypeOptions = enumOptions(PAYMENT_TYPES, paymentTypeKey, t);
const today = toDateInputValue(new Date());

function update<K extends keyof PayFormData>(field: K, value: PayFormData[K]): void {
    (form.value as unknown as PayFormData)[field] = value;
    callValidate(form.value, field);
}

function submit(): void {
    form.value.submit({ preserveScroll: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <dialog class="modal" :class="{ 'modal-open': props.open }">
        <div class="modal-box">
            <h3 class="text-lg font-bold">
                {{ t(props.creditNote ? 'invoice_refund_modal_title' : 'invoice_pay_modal_title') }}
            </h3>

            <FormProvider :form="form">
                <form novalidate class="mt-4 space-y-2" @submit.prevent="submit">
                    <DateInput
                        :model-value="form.paid_on"
                        :label="t('invoice_payment_paid_on')"
                        :max="today"
                        required
                        :error="form.errors.paid_on"
                        @update:model-value="update('paid_on', $event)"
                    />
                    <NumberInput
                        :model-value="form.amount"
                        :label="t('invoice_payment_amount')"
                        :min="0.01"
                        :step="0.01"
                        required
                        :error="form.errors.amount"
                        @update:model-value="update('amount', $event)"
                    />
                    <SelectInput
                        field="payment_type"
                        :label="t('invoice_payment_type')"
                        :options="paymentTypeOptions"
                    />
                    <TextareaInput
                        :model-value="form.note"
                        :label="t('invoice_payment_note')"
                        :rows="2"
                        :error="form.errors.note"
                        @update:model-value="update('note', $event)"
                    />

                    <div class="modal-action">
                        <FormActions
                            :submit-label="t('invoice_pay_modal_submit')"
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
