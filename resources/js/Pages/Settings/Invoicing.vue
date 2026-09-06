<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import FormProvider from '@/Components/Forms/FormProvider.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';

import InvoiceSettingsSupplierCard from '@/Components/Invoices/InvoiceSettingsSupplierCard.vue';
import InvoiceSettingsBankCard from '@/Components/Invoices/InvoiceSettingsBankCard.vue';
import InvoiceNumberFormatField from '@/Components/Invoices/InvoiceNumberFormatField.vue';
import InvoiceTemplatePicker from '@/Components/Invoices/InvoiceTemplatePicker.vue';
import InvoiceSettingsDefaultsCard from '@/Components/Invoices/InvoiceSettingsDefaultsCard.vue';

import { enumOptions, RECURRING_DEFAULT_STATES, recurringDefaultStateKey } from '@/utils/enums';
import type { Breadcrumb } from '@/types';

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
}

const props = defineProps<{
    settings: App.Data.Invoices.InvoiceSettingsData;
}>();

const { t } = useI18n();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('invoicing_settings') }];

const form = useForm<InvoiceSettingsFormData>('put', '/settings/invoicing', { ...props.settings });

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

function submit(): void {
    form.submit();
}
</script>

<template>
    <AppLayout>
        <Header :title="t('invoicing_settings')" :breadcrumbs="breadcrumbs" />
        <p class="mb-6 text-base-content/60">{{ t('invoice_settings_subtitle') }}</p>

        <FormProvider :form="form">
            <form novalidate class="max-w-4xl space-y-6" @submit.prevent="submit">
                <InvoiceSettingsSupplierCard />
                <InvoiceSettingsBankCard />

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

                <InvoiceSettingsDefaultsCard />

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

                <FormActions cancel-href="/" :submit-label="t('save')" :processing="form.processing" />
            </form>
        </FormProvider>
    </AppLayout>
</template>
