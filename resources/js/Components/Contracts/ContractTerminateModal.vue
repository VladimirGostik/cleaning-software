<script setup lang="ts">
import { shallowRef, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import DateInput from '@/Components/Forms/DateInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';

import { toDateInputValue } from '@/utils/date';

interface TerminateFormData {
    terminated_at: string;
    termination_reason: string;
}

const props = defineProps<{
    open: boolean;
    contractId: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

function makeForm(id: string | null) {
    return useForm('post', id ? `/contracts/${id}/terminate` : '', {
        terminated_at: toDateInputValue(new Date()),
        termination_reason: '',
    } as TerminateFormData).transform((data: TerminateFormData) => ({
        terminated_at: data.terminated_at,
        termination_reason: data.termination_reason || null,
    }));
}

const form = shallowRef(makeForm(props.contractId));

watch(
    () => props.contractId,
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
            <h3 class="text-lg font-bold">{{ t('contract_action_terminate') }}</h3>
            <p class="py-2 text-base-content/70">{{ t('contract_terminate_confirm') }}</p>

            <form novalidate class="mt-4 space-y-4" @submit.prevent="submit">
                <DateInput
                    :model-value="form.terminated_at"
                    :label="t('contract_terminate_date')"
                    required
                    :error="form.errors.terminated_at"
                    @update:model-value="form.terminated_at = $event ?? ''"
                />

                <TextareaInput
                    :model-value="form.termination_reason"
                    :label="t('contract_terminate_reason')"
                    :placeholder="t('contract_terminate_reason_placeholder')"
                    :error="form.errors.termination_reason"
                    :rows="3"
                    @update:model-value="form.termination_reason = $event"
                />

                <div class="modal-action">
                    <FormActions
                        :submit-label="t('contract_action_terminate')"
                        :processing="form.processing"
                        @cancel="emit('close')"
                    />
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="emit('close')">close</button>
        </form>
    </dialog>
</template>
