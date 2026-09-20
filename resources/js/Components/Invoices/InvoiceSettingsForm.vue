<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';

import InvoiceSettingsSupplierCard from './InvoiceSettingsSupplierCard.vue';
import InvoiceSettingsBankCard from './InvoiceSettingsBankCard.vue';
import InvoiceNumberFormatField from './InvoiceNumberFormatField.vue';
import InvoiceTemplatePicker from './InvoiceTemplatePicker.vue';
import InvoiceSettingsSignatureCard from './InvoiceSettingsSignatureCard.vue';
import InvoiceSettingsDefaultsCard from './InvoiceSettingsDefaultsCard.vue';

import { enumOptions, RECURRING_DEFAULT_STATES, recurringDefaultStateKey } from '@/utils/enums';
import { signatureIntentToFields, fieldsToSignatureIntent, type SignatureIntent } from './signatureIntent';

interface InvoiceSettingsFormData {
    name: string;
    ico: string | null;
    dic: string | null;
    vat_number: string | null;
    is_vat_payer: boolean;
    address_line: string | null;
    city: string | null;
    postal_code: string | null;
    country: string;
    contact_email: string | null;
    contact_phone: string | null;
    invoice_template: App.Enums.InvoiceTemplateEnum;
    invoice_number_format: string;
    iban: string | null;
    vat_rate: number | null;
    registration_info: string | null;
    recurring_default_state: App.Enums.RecurringDefaultStateEnum;
    swift_bic: string | null;
    default_constant_symbol: string | null;
    default_payment_type: App.Enums.PaymentTypeEnum;
    default_currency: App.Enums.CurrencyEnum;
    default_rounding_mode: App.Enums.RoundingModeEnum;
    signature_uuid: string | null;
    remove_signature: boolean;
}

const props = withDefaults(
    defineProps<{
        settings: App.Data.Invoices.InvoiceSettingsData;
        signature: App.Data.Tenants.TenantSignatureData | null;
        signatureConstraints: App.Data.Invoices.InvoiceSignatureConstraintsData;
        compact?: boolean;
    }>(),
    { compact: false },
);

const emit = defineEmits<{
    saved: [];
    cancel: [];
}>();

const { t } = useI18n();

const form = useForm<InvoiceSettingsFormData>('put', '/settings/invoicing', {
    ...props.settings,
    signature_uuid: null,
    remove_signature: false,
});

form.transform((data: InvoiceSettingsFormData) => ({
    ...data,
    ico: data.ico || null,
    dic: data.dic || null,
    vat_number: data.vat_number || null,
    address_line: data.address_line || null,
    city: data.city || null,
    postal_code: data.postal_code || null,
    contact_email: data.contact_email || null,
    contact_phone: data.contact_phone || null,
    iban: data.iban || null,
    swift_bic: data.swift_bic || null,
    default_constant_symbol: data.default_constant_symbol || null,
}));

const recurringDefaultStateOptions = computed<SelectOption[]>(() =>
    enumOptions(RECURRING_DEFAULT_STATES, recurringDefaultStateKey, t),
);

const signatureIntent = computed<SignatureIntent>(() =>
    fieldsToSignatureIntent({ signature_uuid: form.signature_uuid, remove_signature: form.remove_signature }),
);

function onSignatureIntent(intent: SignatureIntent): void {
    const fields = signatureIntentToFields(intent);
    form.signature_uuid = fields.signature_uuid;
    form.remove_signature = fields.remove_signature;
}

function submit(): void {
    form.submit({
        preserveScroll: true,
        onSuccess: () => {
            form.signature_uuid = null;
            form.remove_signature = false;
            form.defaults({ ...form.data(), signature_uuid: null, remove_signature: false });
            emit('saved');
        },
    });
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate :class="compact ? 'flex h-full flex-col' : ''" @submit.prevent="submit">
            <div :class="compact ? 'flex-1 space-y-6 p-6' : 'space-y-6'">
                <InvoiceSettingsSupplierCard :compact="compact" />
                <InvoiceSettingsBankCard :compact="compact" />

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoice_settings_section_numbering') }}</h2>
                        <InvoiceNumberFormatField />
                    </div>
                </div>

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base">{{ t('invoice_settings_section_templates') }}</h2>
                        <InvoiceTemplatePicker
                            :model-value="form.invoice_template"
                            :error="form.errors.invoice_template"
                            @update:model-value="form.invoice_template = $event"
                        />
                    </div>
                </div>

                <InvoiceSettingsSignatureCard
                    :signature="signature"
                    :constraints="signatureConstraints"
                    :intent="signatureIntent"
                    :error="form.errors.signature_uuid"
                    @update:intent="onSignatureIntent"
                />

                <InvoiceSettingsDefaultsCard :compact="compact" />

                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body space-y-2">
                        <SelectInput
                            field="recurring_default_state"
                            :label="t('invoice_settings_recurring_default_state')"
                            :options="recurringDefaultStateOptions"
                        />
                        <p class="text-sm text-base-content/60">
                            {{ t('invoice_settings_recurring_default_state_hint') }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                :class="
                    compact
                        ? 'sticky bottom-0 z-10 border-t border-base-300 bg-base-100 px-6 py-3'
                        : 'sticky bottom-0 z-10 mt-8 rounded-box border border-base-300 bg-base-100 px-4 py-4 shadow-sm'
                "
            >
                <FormActions
                    :cancel-href="compact ? undefined : '/'"
                    :submit-label="t('save')"
                    :processing="form.processing"
                    @cancel="emit('cancel')"
                />
            </div>
        </form>
    </FormProvider>
</template>
