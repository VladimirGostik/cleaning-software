<script setup lang="ts">
import { computed } from 'vue';
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

withDefaults(defineProps<{ compact?: boolean }>(), { compact: false });

const { t } = useI18n();

const paymentTypeOptions = computed<SelectOption[]>(() => enumOptions(PAYMENT_TYPES, paymentTypeKey, t));
const currencyOptions = computed<SelectOption[]>(() => enumOptions(CURRENCIES, currencyKey, t));
const roundingModeOptions = computed<SelectOption[]>(() => enumOptions(ROUNDING_MODES, roundingModeKey, t));
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('invoice_settings_section_defaults') }}</h2>

            <div class="grid grid-cols-1 gap-4" :class="{ 'md:grid-cols-2': !compact }">
                <TextInput field="default_constant_symbol" :label="t('invoice_settings_default_constant_symbol')" />
                <SelectInput
                    field="default_payment_type"
                    :label="t('invoice_settings_default_payment_type')"
                    :options="paymentTypeOptions"
                />
                <SelectInput
                    field="default_currency"
                    :label="t('invoice_settings_default_currency')"
                    :options="currencyOptions"
                />
                <SelectInput
                    field="default_rounding_mode"
                    :label="t('invoice_settings_default_rounding_mode')"
                    :options="roundingModeOptions"
                />
            </div>
        </div>
    </div>
</template>
