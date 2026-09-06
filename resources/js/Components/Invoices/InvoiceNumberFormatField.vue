<script setup lang="ts">
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { useFormContext } from '@/Components/Forms/useFormContext';
import { callValidate } from '@/Components/Forms/useFieldError';

const PRESETS = ['FA-{YYYY}-{XXXX}', '{YYYY}{XXXX}', '{YYYY}/{XXX}', '{YY}{MM}{XXX}'] as const;

const { t } = useI18n();
const form = useFormContext();

const currentFormat = computed<string>(() =>
    form ? (((form as Record<string, unknown>).invoice_number_format as string) ?? '') : '',
);
const errors = computed(() => (form ? (form.errors as Record<string, string | undefined>) : {}));

const ui = reactive({
    custom: !(PRESETS as readonly string[]).includes(currentFormat.value),
});

const presetOptions = computed<SelectOption[]>(() => [
    ...PRESETS.map((preset) => ({ value: preset, label: preset })),
    { value: 'custom', label: t('invoice_settings_number_format_custom') },
]);

const selectValue = computed<string>(() => (ui.custom ? 'custom' : currentFormat.value));

function onPresetChange(value: string): void {
    if (value === 'custom') {
        ui.custom = true;
        return;
    }
    ui.custom = false;
    if (form) {
        (form as Record<string, unknown>).invoice_number_format = value;
        callValidate(form, 'invoice_number_format');
    }
}

function formatPreview(format: string): string {
    const now = new Date();
    const yyyy = String(now.getFullYear());
    const yy = yyyy.slice(-2);
    const mm = String(now.getMonth() + 1).padStart(2, '0');

    let result = format.replace('{YYYY}', yyyy).replace('{YY}', yy).replace('{MM}', mm);
    result = result.replace(/\{(X+)\}/g, (_match, xs: string) => '1'.padStart(xs.length, '0'));

    return result;
}

const previewNumber = computed(() => formatPreview(currentFormat.value));

const numberFormatHint = computed(() =>
    t('invoice_settings_number_format_hint', {
        yyyy: '{YYYY}',
        yy: '{YY}',
        mm: '{MM}',
        seq: '{X+}',
        seqExample: '{XXXX}',
    }),
);
</script>

<template>
    <div class="space-y-3">
        <SelectInput
            :model-value="selectValue"
            :label="t('invoice_settings_number_format')"
            :options="presetOptions"
            @update:model-value="onPresetChange"
        />

        <TextInput
            v-if="ui.custom"
            field="invoice_number_format"
            :label="t('invoice_settings_number_format_custom_label')"
            required
        />

        <p v-if="errors.invoice_number_format && !ui.custom" class="text-error text-sm">
            {{ errors.invoice_number_format }}
        </p>

        <p class="text-sm text-base-content/60">{{ numberFormatHint }}</p>
        <p class="text-sm font-mono">{{ t('invoice_settings_number_preview') }}: {{ previewNumber }}</p>
    </div>
</template>
