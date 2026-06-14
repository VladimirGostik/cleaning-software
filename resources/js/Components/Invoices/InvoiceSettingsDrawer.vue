<script setup lang="ts">
    import { ref, computed, watch, nextTick } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import { XMarkIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import NumberInput from '@/Components/Forms/NumberInput.vue';
    import SelectInput from '@/Components/Forms/SelectInput.vue';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import InvoiceTemplatePicker from '@/Components/Invoices/InvoiceTemplatePicker.vue';

    interface Props {
        open: boolean;
        settings: App.Data.Invoices.InvoiceSettingsData;
        templateOptions: Array<{ value: string; label: string }>;
        companyName: string;
        isVatPayer: boolean;
        nextNumberPreview?: string | null;
    }

    const props = withDefaults(defineProps<Props>(), {
        nextNumberPreview: null,
    });

    const emit = defineEmits<{
        'update:open': [value: boolean];
        saved: [];
    }>();

    const { t } = useTranslate();

    const drawerRef = ref<HTMLElement | null>(null);
    // eslint-disable-next-line no-restricted-syntax -- transient toast flag, no composable exists
    const showSaved = ref(false);

    watch(
        () => props.open,
        (val) => {
            if (val) nextTick(() => drawerRef.value?.focus());
        },
    );

    type SectionKey = 'zaklad' | 'dph' | 'sablony' | 'cislovanie' | 'opakovane' | 'upominky';
    const activeSection = ref<SectionKey>('zaklad');

    const sections: Array<{ key: SectionKey; label: string }> = [
        { key: 'zaklad', label: t('invoice_settings.section.zaklad') },
        { key: 'dph', label: t('invoice_settings.section.dph') },
        { key: 'sablony', label: t('invoice_settings.section.sablony') },
        { key: 'cislovanie', label: t('invoice_settings.section.cislovanie') },
        { key: 'opakovane', label: t('invoice_settings.section.opakovane') },
        { key: 'upominky', label: t('invoice_settings.section.upominky') },
    ];

    const presets = [
        { value: 'FA-{YYYY}-{XXXX}', label: 'FA-{YYYY}-{XXXX}' },
        { value: '{YYYY}{XXXX}', label: '{YYYY}{XXXX}' },
        { value: '{YYYY}/{XXX}', label: '{YYYY}/{XXX}' },
        { value: '{YY}{MM}{XXX}', label: '{YY}{MM}{XXX}' },
    ] as const;

    const isPreset = (fmt: string): boolean => presets.some((p) => p.value === fmt);

    const form = useForm(
        'put',
        '/settings/invoicing',
        {
            invoice_template: props.settings.invoice_template,
            invoice_number_format: props.settings.invoice_number_format,
            iban: props.settings.iban ?? null,
            vat_rate: props.settings.vat_rate ?? null,
            registration_info: props.settings.registration_info ?? null,
        },
    );

    const customFormat = ref(!isPreset(props.settings.invoice_number_format));

    const presetOptions = computed<SelectOption[]>(() => [
        ...presets.map((p) => ({ value: p.value, label: p.label })),
        { value: 'custom', label: t('invoice_settings.custom_format') },
    ]);

    const selectedPreset = computed({
        get() {
            if (customFormat.value) return 'custom';
            return form.invoice_number_format;
        },
        set(val: string) {
            if (val === 'custom') {
                customFormat.value = true;
            } else {
                customFormat.value = false;
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

    function close() {
        emit('update:open', false);
    }

    function submit() {
        form.submit({
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                emit('saved');
                showSaved.value = true;
                setTimeout(() => {
                    showSaved.value = false;
                }, 3000);
            },
        });
    }
</script>

<template>
    <Teleport to="body">
        <template v-if="open">
            <div class="fixed inset-0 bg-black/40 z-40" @click="close" />
            <aside
                ref="drawerRef"
                role="dialog"
                aria-modal="true"
                aria-labelledby="invoice-settings-title"
                tabindex="-1"
                class="fixed right-0 top-0 h-full w-[680px] bg-base-100 shadow-xl z-50 flex flex-col"
                @keydown.escape="close"
            >
                <!-- Header -->
                <header class="sticky top-0 bg-base-100 border-b border-base-300 px-6 py-4 flex justify-between items-center shrink-0">
                    <h2 id="invoice-settings-title" class="text-lg font-semibold">{{ t('invoice_settings.open') }}</h2>
                    <button class="btn btn-sm btn-ghost btn-circle" type="button" :aria-label="t('common.close')" @click="close">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </header>

                <!-- Body: nav + content -->
                <div class="flex flex-1 overflow-hidden">
                    <!-- Left nav -->
                    <nav class="w-44 shrink-0 border-r border-base-300 py-3 overflow-y-auto">
                        <ul class="menu menu-sm gap-0.5 px-2">
                            <li v-for="section in sections" :key="section.key">
                                <button
                                    type="button"
                                    :aria-current="activeSection === section.key ? 'page' : undefined"
                                    :class="{ 'active': activeSection === section.key }"
                                    @click="activeSection = section.key"
                                >
                                    {{ section.label }}
                                </button>
                            </li>
                        </ul>
                    </nav>

                    <!-- Right content -->
                    <div class="flex-1 overflow-y-auto">
                        <div v-if="showSaved" class="alert alert-success alert-sm mx-6 mt-4">
                            {{ t('invoice_settings.saved') }}
                        </div>

                        <FormProvider :form="form">
                            <form class="p-6 space-y-6 pb-24" novalidate @submit.prevent="submit">

                                <!-- Section: zaklad -->
                                <template v-if="activeSection === 'zaklad'">
                                    <div class="card bg-base-100 border border-base-300">
                                        <div class="card-body space-y-4">
                                            <h3 class="font-semibold text-sm">{{ t('invoice_settings.section.zaklad') }}</h3>

                                            <!-- Company name read-only -->
                                            <div>
                                                <p class="text-xs text-base-content/60 mb-1">{{ t('clients.col.name') }}</p>
                                                <p class="text-sm font-medium">{{ companyName }}</p>
                                            </div>

                                            <!-- IBAN (wired) -->
                                            <TextInput
                                                field="iban"
                                                :label="t('invoice_settings.iban')"
                                                placeholder="SK0000000000000000000000"
                                            />

                                            <!-- Disabled placeholders (NOT in FormProvider scope — pure display) -->
                                        </div>
                                    </div>

                                    <!-- Track B: disabled company detail placeholders — outside FormProvider scope logic -->
                                    <div class="card bg-base-100 border border-base-300 opacity-60">
                                        <div class="card-body space-y-3">
                                            <h3 class="font-semibold text-sm">{{ t('invoice_settings.company.ico') }} / {{ t('invoice_settings.company.dic') }}</h3>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <p class="text-xs text-base-content/60 mb-1">{{ t('invoice_settings.company.ico') }}</p>
                                                    <div class="input input-sm input-bordered w-full bg-base-200 text-base-content/40 flex items-center text-sm">—</div>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-base-content/60 mb-1">{{ t('invoice_settings.company.dic') }}</p>
                                                    <div class="input input-sm input-bordered w-full bg-base-200 text-base-content/40 flex items-center text-sm">—</div>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-base-content/60 mb-1">{{ t('invoice_settings.company.ic_dph') }}</p>
                                                    <div class="input input-sm input-bordered w-full bg-base-200 text-base-content/40 flex items-center text-sm">—</div>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-base-content/60 mb-1">{{ t('invoice_settings.company.swift') }}</p>
                                                    <div class="input input-sm input-bordered w-full bg-base-200 text-base-content/40 flex items-center text-sm">—</div>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="text-xs text-base-content/60 mb-1">{{ t('invoice_settings.company.address') }}</p>
                                                <div class="input input-sm input-bordered w-full bg-base-200 text-base-content/40 flex items-center text-sm">—</div>
                                            </div>
                                            <div>
                                                <p class="text-xs text-base-content/60 mb-1">{{ t('invoice_settings.company.logo') }}</p>
                                                <div class="input input-sm input-bordered w-full bg-base-200 text-base-content/40 flex items-center text-sm">—</div>
                                            </div>
                                            <p class="text-xs text-base-content/40 italic">{{ t('invoice_settings.coming_soon') }}</p>
                                        </div>
                                    </div>

                                    <div class="sticky bottom-0 bg-base-100 border-t border-base-300 -mx-6 px-6 py-3">
                                        <FormActions
                                            :processing="form.processing"
                                            :cancel-label="t('cancel')"
                                            :submit-label="t('invoice_settings.save')"
                                            @cancel="close"
                                        />
                                    </div>
                                </template>

                                <!-- Section: dph -->
                                <template v-if="activeSection === 'dph'">
                                    <div class="card bg-base-100 border border-base-300">
                                        <div class="card-body space-y-4">
                                            <h3 class="font-semibold text-sm">{{ t('invoice_settings.section.dph') }}</h3>

                                            <!-- VAT payer status card (read-only) -->
                                            <div
                                                :class="[
                                                    'rounded-lg border p-3 text-sm',
                                                    isVatPayer
                                                        ? 'border-warning/30 bg-warning/10'
                                                        : 'border-base-300 bg-base-200/50',
                                                ]"
                                            >
                                                <p class="font-medium mb-0.5">{{ t('invoice_settings.vat_status_label') }}</p>
                                                <p class="text-base-content/70">
                                                    {{ isVatPayer ? t('invoice_settings.vat_payer_yes') : t('invoice_settings.vat_payer_no') }}
                                                </p>
                                            </div>

                                            <!-- VAT rate (wired) -->
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

                                    <div class="sticky bottom-0 bg-base-100 border-t border-base-300 -mx-6 px-6 py-3">
                                        <FormActions
                                            :processing="form.processing"
                                            :cancel-label="t('cancel')"
                                            :submit-label="t('invoice_settings.save')"
                                            @cancel="close"
                                        />
                                    </div>
                                </template>

                                <!-- Section: sablony -->
                                <template v-if="activeSection === 'sablony'">
                                    <div class="card bg-base-100 border border-base-300">
                                        <div class="card-body space-y-4">
                                            <h3 class="font-semibold text-sm">{{ t('invoice_settings.section.sablony') }}</h3>
                                            <InvoiceTemplatePicker
                                                v-model="form.invoice_template"
                                                :options="templateOptions"
                                                :error="form.errors.invoice_template"
                                            />
                                        </div>
                                    </div>

                                    <div class="sticky bottom-0 bg-base-100 border-t border-base-300 -mx-6 px-6 py-3">
                                        <FormActions
                                            :processing="form.processing"
                                            :cancel-label="t('cancel')"
                                            :submit-label="t('invoice_settings.save')"
                                            @cancel="close"
                                        />
                                    </div>
                                </template>

                                <!-- Section: cislovanie -->
                                <template v-if="activeSection === 'cislovanie'">
                                    <div class="card bg-base-100 border border-base-300">
                                        <div class="card-body space-y-4">
                                            <h3 class="font-semibold text-sm">{{ t('invoice_settings.section.cislovanie') }}</h3>
                                            <p class="text-xs text-base-content/60">
                                                {{ t('invoice_settings.number_format_hint') }}
                                            </p>

                                            <SelectInput
                                                v-model="selectedPreset"
                                                :options="presetOptions"
                                                :label="t('invoice_settings.number_format')"
                                                :error="form.errors.invoice_number_format"
                                            />

                                            <div v-if="customFormat">
                                                <TextInput
                                                    field="invoice_number_format"
                                                    :label="t('invoice_settings.custom_format_label')"
                                                    placeholder="FA-{YYYY}-{XXXX}"
                                                    required
                                                />
                                            </div>

                                            <!-- Live preview -->
                                            <div class="p-3 bg-base-200 rounded text-sm">
                                                <span class="text-base-content/60">{{ t('invoice_settings.preview') }}: </span>
                                                <span class="font-mono font-medium">{{ previewNumber }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="sticky bottom-0 bg-base-100 border-t border-base-300 -mx-6 px-6 py-3">
                                        <FormActions
                                            :processing="form.processing"
                                            :cancel-label="t('cancel')"
                                            :submit-label="t('invoice_settings.save')"
                                            @cancel="close"
                                        />
                                    </div>
                                </template>

                                <!-- Section: opakovane (Track B: all visual-only, NOT inside FormProvider data) -->
                                <template v-if="activeSection === 'opakovane'">
                                    <!-- Info block -->
                                    <div class="rounded-lg bg-accent/10 border border-accent/30 p-4 text-sm">
                                        <p class="font-medium text-accent">{{ t('invoice_settings.section.opakovane') }}</p>
                                        <p class="text-base-content/70 mt-1 text-xs">{{ t('invoice_settings.coming_soon') }}</p>
                                    </div>

                                    <div class="card bg-base-100 border border-base-300 opacity-60">
                                        <div class="card-body space-y-4">
                                            <!-- Toggle rows -->
                                            <div class="space-y-3">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <p class="text-sm font-medium">{{ t('invoice_settings.recurring.auto_issue') }}</p>
                                                        <p class="text-xs text-base-content/60">{{ t('invoice_settings.recurring.auto_issue_desc') }}</p>
                                                    </div>
                                                    <input type="checkbox" class="toggle toggle-sm" disabled />
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm font-medium">{{ t('invoice_settings.recurring.auto_send') }}</p>
                                                    <input type="checkbox" class="toggle toggle-sm" disabled />
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm font-medium">{{ t('invoice_settings.recurring.draft_only') }}</p>
                                                    <input type="checkbox" class="toggle toggle-sm" disabled />
                                                </div>
                                            </div>

                                            <!-- 2x2 schedule grid -->
                                            <div class="grid grid-cols-2 gap-3 pt-2">
                                                <div>
                                                    <p class="text-xs text-base-content/60 mb-1">{{ t('invoice_settings.section.opakovane') }}</p>
                                                    <div class="input input-sm input-bordered w-full bg-base-200 text-base-content/40 flex items-center text-sm">—</div>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-base-content/60 mb-1">—</p>
                                                    <div class="input input-sm input-bordered w-full bg-base-200 text-base-content/40 flex items-center text-sm">—</div>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-base-content/60 mb-1">—</p>
                                                    <div class="input input-sm input-bordered w-full bg-base-200 text-base-content/40 flex items-center text-sm">—</div>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-base-content/60 mb-1">—</p>
                                                    <div class="input input-sm input-bordered w-full bg-base-200 text-base-content/40 flex items-center text-sm">—</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- Section: upominky (Track B: all visual-only) -->
                                <template v-if="activeSection === 'upominky'">
                                    <div class="rounded-lg bg-accent/10 border border-accent/30 p-4 text-sm">
                                        <p class="font-medium text-accent">{{ t('invoice_settings.section.upominky') }}</p>
                                        <p class="text-base-content/70 mt-1 text-xs">{{ t('invoice_settings.coming_soon') }}</p>
                                    </div>

                                    <div class="card bg-base-100 border border-base-300 opacity-60">
                                        <div class="card-body space-y-4">
                                            <!-- Header toggle -->
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-medium">{{ t('invoice_settings.reminders.enable') }}</p>
                                                <input type="checkbox" class="toggle toggle-sm" disabled />
                                            </div>

                                            <!-- 4 reminder rows -->
                                            <div class="space-y-3 pt-1 border-t border-base-300">
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm">{{ t('invoice_settings.reminders.before_due') }}</p>
                                                    <input type="checkbox" class="toggle toggle-sm toggle-xs" disabled />
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm">{{ t('invoice_settings.reminders.on_due') }}</p>
                                                    <input type="checkbox" class="toggle toggle-sm toggle-xs" disabled />
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm">{{ t('invoice_settings.reminders.after_7') }}</p>
                                                    <input type="checkbox" class="toggle toggle-sm toggle-xs" disabled />
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm">{{ t('invoice_settings.reminders.after_14') }}</p>
                                                    <input type="checkbox" class="toggle toggle-sm toggle-xs" disabled />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                            </form>
                        </FormProvider>
                    </div>
                </div>
            </aside>
        </template>
    </Teleport>
</template>
