<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
import { CONTRACT_SOURCES, contractSourceKey, enumOptions } from '@/utils/enums';

const props = defineProps<{
    modelValue: App.Enums.ContractSourceEnum;
}>();

const emit = defineEmits<{
    'update:modelValue': [source: App.Enums.ContractSourceEnum];
}>();

const { t } = useI18n();

const sourceOptions = computed<RadioOption[]>(() => enumOptions(CONTRACT_SOURCES, contractSourceKey, t));

function onSelect(value: string): void {
    emit('update:modelValue', value as App.Enums.ContractSourceEnum);
}
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body space-y-2">
            <RadioGroup
                :model-value="props.modelValue"
                :label="t('contract_source_label')"
                :options="sourceOptions"
                @update:model-value="onSelect"
            />
            <p class="text-xs text-muted">{{ t(`contract_source_${props.modelValue}_hint`) }}</p>
        </div>
    </div>
</template>
