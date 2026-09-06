<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import NumberInput from '@/Components/Forms/NumberInput.vue';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import { useFormContext } from '@/Components/Forms/useFormContext';
import { callValidate } from '@/Components/Forms/useFieldError';

const { t } = useI18n();
const form = useFormContext();

const vatRate = computed<number | null>(() =>
    form ? ((form as Record<string, unknown>).vat_rate as number | null) : null,
);
const registrationInfo = computed<string>(() =>
    form ? (((form as Record<string, unknown>).registration_info as string | null) ?? '') : '',
);
const errors = computed(() => (form ? (form.errors as Record<string, string | undefined>) : {}));

function updateVatRate(value: number | null): void {
    if (!form) return;
    (form as Record<string, unknown>).vat_rate = value;
    callValidate(form, 'vat_rate');
}

function updateRegistrationInfo(value: string): void {
    if (!form) return;
    (form as Record<string, unknown>).registration_info = value || null;
    callValidate(form, 'registration_info');
}
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('invoice_settings_section_bank') }}</h2>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <TextInput field="iban" :label="t('invoice_settings_iban')" placeholder="SK0000000000000000000000" />
                <TextInput
                    field="swift_bic"
                    :label="t('invoice_settings_swift_bic')"
                    :placeholder="t('invoice_settings_swift_bic_hint')"
                />
                <NumberInput
                    :model-value="vatRate"
                    :label="t('invoice_settings_vat_rate')"
                    :min="0"
                    :max="100"
                    :step="0.01"
                    :error="errors.vat_rate"
                    @update:model-value="updateVatRate"
                />
            </div>

            <TextareaInput
                :model-value="registrationInfo"
                :label="t('invoice_settings_registration_info')"
                :placeholder="t('invoice_settings_registration_info_hint')"
                :rows="2"
                :error="errors.registration_info"
                @update:model-value="updateRegistrationInfo"
            />
        </div>
    </div>
</template>
