<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ListIcon from '~icons/material-symbols/list-alt-outline-rounded';
import DocumentIcon from '~icons/material-symbols/description-outline-rounded';

import ViewModeSwitch from '@/Components/ViewModeSwitch.vue';
import QuoteKindBadge from './QuoteKindBadge.vue';
import { QUOTE_KINDS, quoteKindKey } from '@/utils/enums';

const props = defineProps<{
    modelValue: App.Enums.QuoteKindEnum;
    locked: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [kind: App.Enums.QuoteKindEnum];
}>();

const { t } = useI18n();

const icons = { itemized: ListIcon, document: DocumentIcon } as const;

const options = computed(() =>
    QUOTE_KINDS.map((kind) => ({ value: kind, label: t(quoteKindKey(kind)), icon: icons[kind] })),
);
</script>

<template>
    <div v-if="!props.locked" :class="{ 'pointer-events-none opacity-60': props.disabled }">
        <ViewModeSwitch
            :model-value="props.modelValue"
            :options="options"
            :label="t('quote_form_kind')"
            @update:model-value="emit('update:modelValue', $event as App.Enums.QuoteKindEnum)"
        />
    </div>

    <div v-else class="flex items-center gap-2" :title="t('quote_kind_locked')">
        <span class="text-sm text-base-content/70">{{ t('quote_kind') }}</span>
        <QuoteKindBadge :kind="props.modelValue" />
    </div>
</template>
