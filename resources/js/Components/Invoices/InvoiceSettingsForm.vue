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
import InvoiceSettingsPreviewCard from './InvoiceSettingsPreviewCard.vue';
import InvoiceSettingsSignatureCard from './InvoiceSettingsSignatureCard.vue';
import InvoiceSettingsQuoteCard from './InvoiceSettingsQuoteCard.vue';
import InvoiceSettingsDefaultsCard from './InvoiceSettingsDefaultsCard.vue';
import InvoiceSettingsTextsCard from './InvoiceSettingsTextsCard.vue';
import InvoiceSettingsMailCard from './InvoiceSettingsMailCard.vue';

import { enumOptions, RECURRING_DEFAULT_STATES, recurringDefaultStateKey } from '@/utils/enums';
import { imageIntentToFields, fieldsToImageIntent, type ImageIntent } from './imageIntent';

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
    representative_name: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    peppol_participant_id: string | null;
    invoice_number_format: string;
    iban: string | null;
    vat_rate: number | null;
    registration_info: string | null;
    recurring_default_state: App.Enums.RecurringDefaultStateEnum;
    swift_bic: string | null;
    default_constant_symbol: string | null;
    default_header_text: string | null;
    default_footer_text: string | null;
    invoice_mail_subject: string | null;
    invoice_mail_body: string | null;
    default_payment_type: App.Enums.PaymentTypeEnum;
    default_currency: App.Enums.CurrencyEnum;
    default_rounding_mode: App.Enums.RoundingModeEnum;
    signature_uuid: string | null;
    remove_signature: boolean;
    logo_uuid: string | null;
    remove_logo: boolean;
    default_quote_included_text: string | null;
    signing_place: string | null;
}

const props = withDefaults(
    defineProps<{
        settings: App.Data.Invoices.InvoiceSettingsData;
        signature: App.Data.Tenants.TenantSignatureData | null;
        signatureConstraints: App.Data.Invoices.InvoiceSignatureConstraintsData;
        logo?: App.Data.Tenants.TenantLogoData | null;
        logoConstraints?: App.Data.Invoices.TenantLogoConstraintsData | null;
        mailPlaceholders: App.Data.Invoices.InvoiceMailPlaceholderCatalogData;
        compact?: boolean;
    }>(),
    { compact: false, logo: null, logoConstraints: null },
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
    logo_uuid: null,
    remove_logo: false,
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
    representative_name: data.representative_name || null,
    peppol_participant_id: data.peppol_participant_id || null,
    iban: data.iban || null,
    swift_bic: data.swift_bic || null,
    default_constant_symbol: data.default_constant_symbol || null,
    default_header_text: data.default_header_text || null,
    default_footer_text: data.default_footer_text || null,
    invoice_mail_subject: data.invoice_mail_subject || null,
    invoice_mail_body: data.invoice_mail_body || null,
    default_quote_included_text: data.default_quote_included_text || null,
    signing_place: data.signing_place || null,
}));

const recurringDefaultStateOptions = computed<SelectOption[]>(() =>
    enumOptions(RECURRING_DEFAULT_STATES, recurringDefaultStateKey, t),
);

const signatureImageIntent = computed<ImageIntent>(() =>
    fieldsToImageIntent(form.signature_uuid, form.remove_signature),
);

const logoIntent = computed<ImageIntent>(() => fieldsToImageIntent(form.logo_uuid, form.remove_logo));

function onSignatureIntent(intent: ImageIntent): void {
    const fields = imageIntentToFields(intent);
    form.signature_uuid = fields.uuid;
    form.remove_signature = fields.remove;
}

function onLogoIntent(intent: ImageIntent): void {
    const fields = imageIntentToFields(intent);
    form.logo_uuid = fields.uuid;
    form.remove_logo = fields.remove;
}

function submit(): void {
    form.submit({
        preserveScroll: true,
        onSuccess: () => {
            form.signature_uuid = null;
            form.remove_signature = false;
            form.logo_uuid = null;
            form.remove_logo = false;
            form.defaults({
                ...form.data(),
                signature_uuid: null,
                remove_signature: false,
                logo_uuid: null,
                remove_logo: false,
            });
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

                <InvoiceSettingsSignatureCard
                    :signature="signature"
                    :constraints="signatureConstraints"
                    :intent="signatureImageIntent"
                    :error="form.errors.signature_uuid"
                    @update:intent="onSignatureIntent"
                />

                <InvoiceSettingsQuoteCard
                    v-if="logoConstraints"
                    v-model:included-text="form.default_quote_included_text"
                    :logo="logo"
                    :constraints="logoConstraints"
                    :intent="logoIntent"
                    :logo-error="form.errors.logo_uuid"
                    :included-error="form.errors.default_quote_included_text"
                    @update:intent="onLogoIntent"
                />

                <InvoiceSettingsDefaultsCard :compact="compact" />
                <InvoiceSettingsTextsCard
                    v-model:header-text="form.default_header_text"
                    v-model:footer-text="form.default_footer_text"
                    :header-error="form.errors.default_header_text"
                    :footer-error="form.errors.default_footer_text"
                />
                <InvoiceSettingsMailCard
                    v-model:subject="form.invoice_mail_subject"
                    v-model:body="form.invoice_mail_body"
                    :tokens="mailPlaceholders.tokens"
                    :subject-error="form.errors.invoice_mail_subject"
                    :body-error="form.errors.invoice_mail_body"
                />

                <InvoiceSettingsPreviewCard />

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
