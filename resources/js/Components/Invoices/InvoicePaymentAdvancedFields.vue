<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import TextInput from '@/Components/Forms/TextInput.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import {
    CURRENCIES,
    currencyKey,
    enumOptions,
    PAYMENT_TYPES,
    paymentTypeKey,
    ROUNDING_MODES,
    roundingModeKey,
} from '@/utils/enums';

const props = defineProps<{
    open: boolean;
    hasOverride: boolean;
    hasError: boolean;
    summary: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { t } = useI18n();

const paymentTypeOptions = computed<SelectOption[]>(() => enumOptions(PAYMENT_TYPES, paymentTypeKey, t));
const currencyOptions = computed<SelectOption[]>(() => enumOptions(CURRENCIES, currencyKey, t));
const roundingModeOptions = computed<SelectOption[]>(() => enumOptions(ROUNDING_MODES, roundingModeKey, t));

const detailsRef = ref<(HTMLElement & { open: boolean }) | null>(null);

function onToggle(event: Event): void {
    const el = event.target as HTMLElement & { open: boolean };
    emit('update:open', el.open);
}

// <details> flips its own open prop natively on click; re-set to an already-current value can miss the DOM, so sync it imperatively post-flush as a safeguard.
watch(
    () => props.open,
    (v) => {
        if (detailsRef.value && detailsRef.value.open !== v) {
            detailsRef.value.open = v;
        }
    },
    { flush: 'post' },
);
</script>

<template>
    <details
        ref="detailsRef"
        class="collapse collapse-arrow rounded-box border border-base-300 bg-base-100"
        :open="open"
        @toggle="onToggle"
    >
        <summary class="collapse-title flex min-h-0 items-center gap-2 py-3 text-sm font-medium">
            {{ t('invoice_section_advanced') }}
            <span v-if="hasOverride" class="badge badge-warning badge-sm">
                {{ t('invoice_advanced_differs_from_settings') }}
            </span>
            <span v-if="hasError" class="badge badge-error badge-sm">{{ t('invoice_advanced_has_errors') }}</span>
            <span class="ml-auto hidden truncate text-xs font-normal text-base-content/60 sm:inline">
                {{ summary }}
            </span>
        </summary>

        <div class="collapse-content">
            <p class="mb-3 text-xs text-base-content/60">{{ t('invoice_advanced_hint') }}</p>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <SelectInput
                    field="payment_type"
                    :label="t('invoice_pdf_payment_type')"
                    :options="paymentTypeOptions"
                />
                <SelectInput field="currency" :label="t('invoice_currency')" :options="currencyOptions" />
                <SelectInput field="rounding_mode" :label="t('invoice_rounding_mode')" :options="roundingModeOptions" />
                <TextInput field="constant_symbol" :label="t('invoice_pdf_constant_symbol')" />
                <TextInput field="specific_symbol" :label="t('invoice_pdf_specific_symbol')" />
            </div>
        </div>
    </details>
</template>
