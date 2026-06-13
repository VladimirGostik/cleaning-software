<script setup lang="ts">
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import TextareaInput from '@/Components/Forms/TextareaInput.vue';
    import FormField from '@/Components/Forms/FormField.vue';
    import NumberInput from '@/Components/Forms/NumberInput.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    interface Props {
        settings: App.Data.Invoices.InvoiceSettingsData;
        templateOptions: Array<{ value: string; label: string }>;
        nextNumberPreview?: string | null;
    }

    const props = withDefaults(defineProps<Props>(), {
        nextNumberPreview: null,
    });

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    interface InvoiceSettingsFormData {
        invoice_template: App.Enums.InvoiceTemplateEnum;
        invoice_number_format: string;
        custom_format: boolean;
        iban: string | null;
        vat_rate: number | null;
        registration_info: string | null;
    }

    const presets = [
        { value: 'FA-{YYYY}-{XXXX}', label: 'FA-{YYYY}-{XXXX}' },
        { value: '{YYYY}{XXXX}', label: '{YYYY}{XXXX}' },
        { value: '{YYYY}/{XXX}', label: '{YYYY}/{XXX}' },
        { value: '{YY}{MM}{XXX}', label: '{YY}{MM}{XXX}' },
    ] as const;

    const isPreset = (fmt: string): boolean => presets.some((p) => p.value === fmt);

    const form = useForm<InvoiceSettingsFormData>(
        'put',
        '/settings/invoicing',
        {
            invoice_template: props.settings.invoice_template,
            invoice_number_format: props.settings.invoice_number_format,
            custom_format: !isPreset(props.settings.invoice_number_format),
            iban: props.settings.iban ?? null,
            vat_rate: props.settings.vat_rate ?? null,
            registration_info: props.settings.registration_info ?? null,
        },
    );

    const templateCards = computed(() =>
        props.templateOptions.map((opt) => ({
            value: opt.value as App.Enums.InvoiceTemplateEnum,
            label: opt.label,
        })),
    );

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

    function selectTemplate(val: App.Enums.InvoiceTemplateEnum) {
        form.invoice_template = val;
    }

    function submit() {
        form.submit();
    }
</script>

<template>
    <div class="max-w-3xl mx-auto">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <PageHeader
            :title="t('invoice_settings.title')"
            :subtitle="t('invoice_settings.subtitle')"
        />

        <FormProvider :form="form">
            <form novalidate @submit.prevent="submit">
                <div class="space-y-6">
                    <!-- Template picker -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('invoice_settings.template') }}</h2>
                            <p v-if="form.errors.invoice_template" class="text-error text-sm">
                                {{ form.errors.invoice_template }}
                            </p>
                            <div
                                class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-2"
                                role="radiogroup"
                                :aria-label="t('invoice_settings.template')"
                            >
                                <button
                                    v-for="card in templateCards"
                                    :key="card.value"
                                    type="button"
                                    role="radio"
                                    :aria-checked="form.invoice_template === card.value"
                                    :class="[
                                        'border-2 rounded-lg p-4 text-left transition cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary',
                                        form.invoice_template === card.value
                                            ? 'border-primary bg-primary/5'
                                            : 'border-base-300 hover:border-base-content/30',
                                    ]"
                                    @click="selectTemplate(card.value)"
                                    @keydown.enter.prevent="selectTemplate(card.value)"
                                    @keydown.space.prevent="selectTemplate(card.value)"
                                >
                                    <!-- Template visual preview placeholder -->
                                    <div class="h-20 bg-base-200 rounded mb-2 flex items-center justify-center">
                                        <span class="text-xs text-base-content/40 uppercase tracking-wide">
                                            {{ card.label }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-medium">{{ card.label }}</p>
                                    <p
                                        v-if="form.invoice_template === card.value"
                                        class="text-xs text-primary mt-0.5"
                                    >
                                        {{ t('invoice_settings.template_selected') }}
                                    </p>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Number format -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="card-title text-base">{{ t('invoice_settings.number_format') }}</h2>
                            <p class="text-xs text-base-content/60 mb-3">
                                {{ t('invoice_settings.number_format_hint') }}
                            </p>

                            <FormField :label="t('invoice_settings.number_format')">
                                <select
                                    :value="selectedPreset"
                                    class="select w-full"
                                    :class="{ 'select-error': form.errors.invoice_number_format }"
                                    :aria-invalid="form.errors.invoice_number_format ? 'true' : undefined"
                                    @change="selectedPreset = ($event.target as HTMLSelectElement).value"
                                >
                                    <option v-for="p in presets" :key="p.value" :value="p.value">
                                        {{ p.label }}
                                    </option>
                                    <option value="custom">{{ t('invoice_settings.custom_format') }}</option>
                                </select>
                            </FormField>

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
                                <span class="text-base-content/60">{{ t('invoice_settings.preview') }}: </span>
                                <span class="font-mono font-medium">{{ previewNumber }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- IBAN + VAT -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <TextInput
                                    field="iban"
                                    :label="t('invoice_settings.iban')"
                                    placeholder="SK0000000000000000000000"
                                />
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
                    </div>

                    <!-- Registration info -->
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body">
                            <TextareaInput
                                field="registration_info"
                                :label="t('invoice_settings.registration_info')"
                                :placeholder="t('invoice_settings.registration_info_hint')"
                                :rows="2"
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
            </form>
        </FormProvider>
    </div>
</template>
