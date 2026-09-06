<script setup lang="ts">
import { nextTick, ref, useId, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import ToggleInput from '@/Components/Forms/ToggleInput.vue';
import ColorSwatchPicker from '@/Components/Forms/ColorSwatchPicker.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import TenantSupplierFields from './TenantSupplierFields.vue';
import type { TenantColorOption } from '@/types';

interface AddTenantFormData {
    name: string;
    ico: string;
    dic: string | null;
    vat_number: string | null;
    is_vat_payer: boolean;
    address_line: string | null;
    city: string | null;
    postal_code: string | null;
    country: string;
    contact_email: string | null;
    contact_phone: string | null;
    iban: string | null;
    swift_bic: string | null;
    color: App.Enums.TenantColorEnum | null;
    copy_settings: boolean;
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
    dic: null,
    vat_number: null,
    is_vat_payer: false,
    address_line: null,
    city: null,
    postal_code: null,
    country: 'SK',
    contact_email: null,
    contact_phone: null,
    iban: null,
    swift_bic: null,
    color: null,
    copy_settings: false,
});

form.transform((data: AddTenantFormData) => ({
    ...data,
    dic: data.dic || null,
    vat_number: data.vat_number || null,
    address_line: data.address_line || null,
    city: data.city || null,
    postal_code: data.postal_code || null,
    contact_email: data.contact_email || null,
    contact_phone: data.contact_phone || null,
    iban: data.iban || null,
    swift_bic: data.swift_bic || null,
}));

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
        <div ref="modalBox" class="modal-box max-w-2xl">
            <div class="flex items-center justify-between mb-4">
                <h3 :id="titleId" class="text-lg font-bold">{{ t('tenant_add_title') }}</h3>
                <button type="button" class="btn btn-sm btn-ghost btn-circle" :aria-label="t('close')" @click="close">
                    <XMarkIcon class="size-4" />
                </button>
            </div>

            <FormProvider :form="form">
                <form novalidate @submit.prevent="submit">
                    <div class="flex flex-col gap-6">
                        <TenantSupplierFields />

                        <div class="space-y-4">
                            <h4 class="font-semibold text-sm">{{ t('tenant_add_section_settings') }}</h4>
                            <ColorSwatchPicker field="color" :colors="colors" :label="t('tenant_add_color')" />
                            <ToggleInput field="copy_settings" :label="t('tenant_add_copy_settings')" />
                            <p class="text-sm text-base-content/60">{{ t('tenant_add_copy_settings_hint') }}</p>
                        </div>
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
