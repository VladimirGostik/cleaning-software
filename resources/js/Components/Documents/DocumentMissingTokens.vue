<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import XMarkIcon from '~icons/material-symbols/close-outline-rounded';

const props = withDefaults(
    defineProps<{
        tokens: { key: string; label: string }[];
        summary: string;
        pickable?: boolean;
    }>(),
    { pickable: false },
);

const emit = defineEmits<{
    pick: [key: string];
}>();

const { t } = useI18n();

const expanded = ref(false);
const dismissedSignature = ref<string | null>(null);

const signature = computed(() =>
    props.tokens
        .map((token) => token.key)
        .sort()
        .join('|'),
);

const visible = computed(() => props.tokens.length > 0 && signature.value !== dismissedSignature.value);

function dismiss(): void {
    dismissedSignature.value = signature.value;
    expanded.value = false;
}
</script>

<template>
    <div v-if="visible" class="alert alert-warning alert-soft shrink-0" role="status">
        <div class="w-full">
            <div class="flex items-center gap-2">
                <p class="min-w-0 flex-1 text-sm font-medium">{{ summary }}</p>
                <button
                    type="button"
                    class="btn btn-ghost btn-sm"
                    :aria-expanded="expanded"
                    aria-controls="document-missing-list"
                    @click="expanded = !expanded"
                >
                    {{ expanded ? t('document_missing_hide') : t('document_missing_show') }}
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-sm btn-square"
                    :aria-label="t('document_missing_dismiss')"
                    :title="t('document_missing_dismiss')"
                    @click="dismiss"
                >
                    <XMarkIcon aria-hidden="true" />
                </button>
            </div>
            <ul v-if="expanded" id="document-missing-list" class="mt-2 flex max-h-32 flex-wrap gap-1 overflow-y-auto">
                <li v-for="token in tokens" :key="token.key">
                    <button
                        v-if="pickable"
                        type="button"
                        class="kg-chip kg-chip--neutral cursor-pointer"
                        :aria-label="t('document_missing_insert', { label: token.label })"
                        @click="emit('pick', token.key)"
                    >
                        {{ token.label }}
                    </button>
                    <span v-else class="kg-chip kg-chip--neutral">{{ token.label }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
