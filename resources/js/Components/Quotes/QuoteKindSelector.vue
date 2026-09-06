<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
import QuoteKindBadge from './QuoteKindBadge.vue';
import { enumOptions, QUOTE_KINDS, quoteKindKey } from '@/utils/enums';

const props = defineProps<{
    modelValue: App.Enums.QuoteKindEnum;
    locked: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [kind: App.Enums.QuoteKindEnum];
}>();

const { t } = useI18n();

const kindOptions = computed<RadioOption[]>(() => enumOptions(QUOTE_KINDS, quoteKindKey, t));

const hintKey = computed(() =>
    props.modelValue === 'document' ? 'quote_kind_document_hint' : 'quote_kind_itemized_hint',
);

function onSelect(value: string): void {
    emit('update:modelValue', value as App.Enums.QuoteKindEnum);
}
</script>

<template>
    <div v-if="!props.locked" class="space-y-2">
        <RadioGroup
            :model-value="props.modelValue"
            :label="t('quote_form_kind')"
            :options="kindOptions"
            :disabled="props.disabled"
            @update:model-value="onSelect"
        />
        <p class="text-xs text-base-content/60">{{ t(hintKey) }}</p>
    </div>

    <div v-else class="space-y-2">
        <h2 class="card-title text-base">
            {{ t('quote_kind') }}
            <QuoteKindBadge :kind="props.modelValue" />
        </h2>
        <p class="text-xs text-base-content/60">{{ t('quote_kind_locked') }}</p>
    </div>
</template>
