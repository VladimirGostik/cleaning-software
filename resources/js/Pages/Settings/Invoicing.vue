<script setup lang="ts">
    import { computed, reactive } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import TextareaInput from '@/Components/Forms/TextareaInput.vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import NumberInput from '@/Components/Forms/NumberInput.vue';
    import InvoiceTemplatePicker from '@/Components/Invoices/InvoiceTemplatePicker.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    interface Props {
        settings: App.Data.Invoices.InvoiceSettingsData;
        templateOptions: Array<{ value: string; label: string }>;
        nextNumberPreview?: string | null;
        isVatPayer?: boolean;
        paymentTypeOptions?: SelectOption[];
        currencyOptions?: SelectOption[];
        roundingModeOptions?: SelectOption[];
    }

    const props = withDefaults(defineProps<Props>(), {
        nextNumberPreview: null,
        isVatPayer: undefined,
        paymentTypeOptions: () => [],
        currencyOptions: () => [],
        roundingModeOptions: () => [],
    });

    const { t } = useTranslate();
    const pageProps = usePageProps();

    type SectionKey = 'basic' | 'vat' | 'templates' | 'numbering' | 'recurring' | 'reminders';

    const nav = reactive<{ section: SectionKey }>({ section: 'basic' });

    const sections: Array<{ key: SectionKey; label: string }> = [
        { key: 'basic', label: t('invoice_settings.section.basic') },
        { key: 'vat', label: t('invoice_settings.section.vat') },
        { key: 'templates', label: t('invoice_settings.section.templates') },
        { key: 'numbering', label: t('invoice_settings.section.numbering') },
        { key: 'recurring', label: t('invoice_settings.section.recurring') },
        { key: 'reminders', label: t('invoice_settings.section.reminders') },
    ];

    interface InvoiceSettingsFormData {
        invoice_template: App.Enums.InvoiceTemplateEnum;
        invoice_number_format: string;
        custom_format: boolean;
        iban: string | null;
        swift_bic: string | null;
        vat_rate: number | null;
        registration_info: string | null;
        recurring_default_state: App.Enums.RecurringDefaultStateEnum;
        default_constant_symbol: string | null;
        default_payment_type: App.Enums.PaymentTypeEnum;
        default_currency: App.Enums.CurrencyEnum;
        default_rounding_mode: App.Enums.RoundingModeEnum;
    }

    const presets = [
        { value: 'FA-{YYYY}-{XXXX}', label: 'FA-{YYYY}-{XXXX}' },
        { value: '{YYYY}{XXXX}', label: '{YYYY}{XXXX}' },
        { value: '{YYYY}/{XXX}', label: '{YYYY}/{XXX}' },
        { value: '{YY}{MM}{XXX}', label: '{YY}{MM}{XXX}' },
    ] as const;

    const isPreset = (fmt: string): boolean => presets.some((p) => p.value === fmt);

    const form = useForm<InvoiceSettingsFormData>('put', '/settings/invoicing', {
        invoice_template: props.settings.invoice_template,
        invoice_number_format: props.settings.invoice_number_format,
        custom_format: !isPreset(props.settings.invoice_number_format),
        iban: props.settings.iban ?? null,
        swift_bic: props.settings.swift_bic ?? null,
        vat_rate: props.settings.vat_rate ?? null,
        registration_info: props.settings.registration_info ?? null,
        recurring_default_state: props.settings.recurring_default_state,
        default_constant_symbol: props.settings.default_constant_symbol ?? null,
        default_payment_type: props.settings.default_payment_type,
        default_currency: props.settings.default_currency,
        default_rounding_mode: props.settings.default_rounding_mode,
    });

    const presetOptions = computed<SelectOption[]>(() => [
        ...presets.map((p) => ({ value: p.value, label: p.label })),
        { value: 'custom', label: t('invoice_settings.custom_format') },
    ]);

    const defaultStateOptions = computed<SelectOption[]>(() => [
        { value: 'draft', label: t('recurring_invoices.settings.state_draft') },
        { value: 'issued', label: t('recurring_invoices.settings.state_issued') },
    ]);

    const selectedPreset = computed({
        get() {
            if (form.custom_format) return 'custom';
            return form.invoice_number_format;
        },
        set(val: string) {
            if (val === 'custom') {
                form.custom_format = true;
            } else {
                form.custom_format = false;
                form.invoice_number_format = val;
            }
        },
    });

    const previewNumber = computed<string>(() => {
        if (props.nextNumberPreview) return props.nextNumberPreview;
        return form.invoice_number_format
            .replace('{YYYY}', String(new Date().getFullYear()))
            .replace('{YY}', String(new Date().getFullYear()).slice(-2))
            .replace('{MM}', String(new Date().getMonth() + 1).padStart(2, '0'))
            .replace(/\{(X+)\}/g, (_, xs: string) => '1'.padStart(xs.length, '0'));
    });

    function submit() {
        form.submit();
    }
</script>

<template>
    <div class="max-w-5xl mx-auto">
        <div v-if="pageProps.flash.success" class="alert alert-success mb-4">
            <span>{{ pageProps.flash.success }}</span>
        </div>

        <PageHeader :title="t('invoice_settings.title')" :subtitle="t('invoice_settings.subtitle')" />

        <div class="grid grid-cols-[200px_1fr] gap-6">
            <!-- Left nav -->
            <div>
                <ul class="menu menu-sm bg-base-100 rounded-box shadow-sm p-2 gap-0.5">
                    <li v-for="section in sections" :key="section.key">
                        <button
                            type="button"
                            :class="{ active: nav.section === section.key }"
                            @click="nav.section = section.key"
                        >
                            {{ section.label }}
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Right content -->
            <div>
                <FormProvider :form="form">
                    <form novalidate @submit.prevent="submit">
                        <!-- Základné (IBAN + SWIFT + registration info + defaults) -->
                        <div v-if="nav.section === 'basic'" class="space-y-6">
                            <div class="card bg-base-100 shadow-sm">
                                <div class="card-body">
                                    <h2 class="card-title text-base">
                                        {{ t('invoice_settings.section.basic') }}
                                    </h2>
                                    <TextInput
                                        field="iban"
                                        :label="t('invoice_settings.iban')"
                                        placeholder="SK0000000000000000000000"
                                    />
                                    <TextInput
                                        field="swift_bic"
                                        :label="t('invoice_settings.swift_bic')"
                                        :placeholder="t('invoice_settings.swift_bic_hint')"
                                    />
                                    <TextareaInput
                                        field="registration_info"
                                        :label="t('invoice_settings.registration_info')"
                                        :placeholder="t('invoice_settings.registration_info_hint')"
                                        :rows="2"
                                    />
                                </div>
                            </div>

                            <!-- Invoice defaults sub-card -->
                            <div class="card bg-base-100 shadow-sm">
                                <div class="card-body">
                                    <h2 class="card-title text-base">
                                        {{ t('invoice_settings.section.defaults') }}
                                    </h2>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <TextInput
                                            field="default_constant_symbol"
                                            :label="t('invoice_settings.default_constant_symbol')"
                                        />
                                        <SelectInput
                                            field="default_payment_type"
                                            :label="t('invoice_settings.default_payment_type')"
                                            :options="paymentTypeOptions"
                                        />
                                        <SelectInput
                                            field="default_currency"
                                            :label="t('invoice_settings.default_currency')"
                                            :options="currencyOptions"
                                        />
                                        <SelectInput
                                            field="default_rounding_mode"
                                            :label="t('invoice_settings.default_rounding')"
                                            :options="roundingModeOptions"
                                        />
                                    </div>
                                </div>
                            </div>

                            <FormActions
                                cancel-href="/dashboard"
                                :cancel-label="t('cancel')"
                                :submit-label="t('invoice_settings.save')"
                                :processing="form.processing"
                            />
                        </div>

                        <!-- DPH -->
                        <div v-if="nav.section === 'vat'" class="space-y-6">
                            <div class="card bg-base-100 shadow-sm">
                                <div class="card-body">
                                    <h2 class="card-title text-base">
                                        {{ t('invoice_settings.section.vat') }}
                                    </h2>

                                    <!-- VAT status info card -->
                                    <div
                                        class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm"
                                    >
                                        <p class="font-medium mb-0.5">
                                            {{ t('invoice_settings.vat_status_label') }}
                                        </p>
                                        <p v-if="isVatPayer === true" class="text-base-content/70">
                                            {{ t('invoice_settings.vat_payer_yes') }}
                                        </p>
                                        <p v-else-if="isVatPayer === false" class="text-base-content/70">
                                            {{ t('invoice_settings.vat_payer_no') }}
                                        </p>
                                        <p v-else class="text-base-content/50 italic">
                                            {{ t('invoice_settings.vat_payer_unknown') }}
                                        </p>
                                    </div>

                                    <NumberInput
                                        v-model="form.vat_rate"
                                        :label="t('invoice_settings.vat_rate')"
                                        :min="0"
                                        :max="100"
                                        :step="0.01"
                                        :error="(form.errors as Record<string, string>)['vat_rate']"
                                    />
                                </div>
                            </div>
                            <FormActions
                                cancel-href="/dashboard"
                                :cancel-label="t('cancel')"
                                :submit-label="t('invoice_settings.save')"
                                :processing="form.processing"
                            />
                        </div>

                        <!-- Šablóny -->
                        <div v-if="nav.section === 'templates'" class="space-y-6">
                            <div class="card bg-base-100 shadow-sm">
                                <div class="card-body">
                                    <h2 class="card-title text-base">{{ t('invoice_settings.template') }}</h2>
                                    <InvoiceTemplatePicker
                                        v-model="form.invoice_template"
                                        :options="templateOptions"
                                        :error="form.errors.invoice_template"
                                    />
                                </div>
                            </div>
                            <FormActions
                                cancel-href="/dashboard"
                                :cancel-label="t('cancel')"
                                :submit-label="t('invoice_settings.save')"
                                :processing="form.processing"
                            />
                        </div>

                        <!-- Číslovanie -->
                        <div v-if="nav.section === 'numbering'" class="space-y-6">
                            <div class="card bg-base-100 shadow-sm">
                                <div class="card-body">
                                    <h2 class="card-title text-base">
                                        {{ t('invoice_settings.number_format') }}
                                    </h2>
                                    <p class="text-xs text-base-content/60 mb-3">
                                        {{ t('invoice_settings.number_format_hint') }}
                                    </p>

                                    <SelectInput
                                        v-model="selectedPreset"
                                        :options="presetOptions"
                                        :label="t('invoice_settings.number_format')"
                                        :error="form.errors.invoice_number_format"
                                    />

                                    <div v-if="form.custom_format" class="mt-3">
                                        <TextInput
                                            field="invoice_number_format"
                                            :label="t('invoice_settings.custom_format_label')"
                                            placeholder="FA-{YYYY}-{XXXX}"
                                            required
                                        />
                                    </div>

                                    <!-- Live preview -->
                                    <div class="mt-3 p-3 bg-base-200 rounded text-sm">
                                        <span class="text-base-content/60"
                                            >{{ t('invoice_settings.preview') }}:
                                        </span>
                                        <span class="font-mono font-medium">{{ previewNumber }}</span>
                                    </div>
                                </div>
                            </div>
                            <FormActions
                                cancel-href="/dashboard"
                                :cancel-label="t('cancel')"
                                :submit-label="t('invoice_settings.save')"
                                :processing="form.processing"
                            />
                        </div>

                        <!-- Opakované faktúry -->
                        <div v-if="nav.section === 'recurring'" class="space-y-6">
                            <div class="card bg-base-100 shadow-sm">
                                <div class="card-body">
                                    <h2 class="card-title text-base">
                                        {{ t('invoice_settings.section.recurring') }}
                                    </h2>
                                    <SelectInput
                                        field="recurring_default_state"
                                        :label="t('recurring_invoices.settings.default_state')"
                                        :options="defaultStateOptions"
                                    />
                                </div>
                            </div>
                            <FormActions
                                cancel-href="/dashboard"
                                :cancel-label="t('cancel')"
                                :submit-label="t('invoice_settings.save')"
                                :processing="form.processing"
                            />
                        </div>

                        <!-- Upomienky (placeholder) -->
                        <div v-if="nav.section === 'reminders'">
                            <div class="card bg-base-100 shadow-sm">
                                <div class="card-body items-center text-center py-12">
                                    <p class="text-base-content/50 text-sm">
                                        {{ t('invoice_settings.section.coming_soon') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </form>
                </FormProvider>
            </div>
        </div>
    </div>
</template>
