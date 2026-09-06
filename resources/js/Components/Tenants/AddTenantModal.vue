<script setup lang="ts">
import { nextTick, ref, useId, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import ColorSwatchPicker from '@/Components/Forms/ColorSwatchPicker.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import type { TenantColorOption } from '@/types';

interface AddTenantFormData {
    name: string;
    ico: string;
    color: App.Enums.TenantColorEnum | null;
    copy_settings: boolean;
    leader_email: string | null;
}

const props = defineProps<{ open: boolean; colors: TenantColorOption[] }>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();
const titleId = useId();
const modalBox = ref<HTMLElement | null>(null);

const form = useForm<AddTenantFormData>('post', '/tenants', {
    name: '',
    ico: '',
    color: null,
    copy_settings: false,
    leader_email: null,
});

function close() {
    form.clearErrors();
    emit('close');
}

function submit() {
    form.submit({
        onSuccess: () => {
            form.reset();
            close();
        },
    });
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            void nextTick(() => modalBox.value?.querySelector<HTMLInputElement>('input')?.focus());
        }
    },
);
</script>

<template>
    <dialog
        class="modal"
        :class="{ 'modal-open': open }"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        @keydown.esc.prevent="close"
        @cancel.prevent="close"
    >
        <div ref="modalBox" class="modal-box">
            <div class="flex items-center justify-between mb-4">
                <h3 :id="titleId" class="text-lg font-bold">{{ t('tenant_add_title') }}</h3>
                <button type="button" class="btn btn-sm btn-ghost btn-circle" :aria-label="t('close')" @click="close">
                    <XMarkIcon class="size-4" />
                </button>
            </div>

            <FormProvider :form="form">
                <form novalidate @submit.prevent="submit">
                    <div class="flex flex-col gap-4">
                        <TextInput field="name" :label="t('tenant_add_name')" required />
                        <TextInput field="ico" :label="t('tenant_add_ico')" required />
                        <ColorSwatchPicker field="color" :colors="colors" :label="t('tenant_add_color')" />
                        <ToggleInput field="copy_settings" :label="t('tenant_add_copy_settings')" />
                        <TextInput
                            field="leader_email"
                            type="email"
                            :label="t('tenant_add_leader_email')"
                            autocomplete="email"
                        />
                    </div>

                    <div class="modal-action">
                        <FormActions
                            :submit-label="t('tenant_add_submit')"
                            :processing="form.processing"
                            @cancel="close"
                        />
                    </div>
                </form>
            </FormProvider>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="button" @click="close">close</button>
        </form>
    </dialog>
</template>
