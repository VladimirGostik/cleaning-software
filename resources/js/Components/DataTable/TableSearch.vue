<script setup lang="ts">
import { KG_ICONS } from '@/icons/kegosIcons';
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string | null;
        disabled?: boolean;
        searchableAttributes?: string[];
        debounceMs?: number;
    }>(),
    {
        modelValue: '',
        disabled: false,
        searchableAttributes: () => [],
        debounceMs: 400,
    },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const value = ref(props.modelValue ?? '');
let timer: number | undefined;

watch(
    () => props.modelValue,
    (next) => {
        value.value = next ?? '';
    },
);

function update(next: string) {
    value.value = next;
    window.clearTimeout(timer);
    timer = window.setTimeout(() => emit('update:modelValue', next), props.debounceMs);
}

function clear() {
    window.clearTimeout(timer);
    value.value = '';
    emit('update:modelValue', '');
}
</script>

<template>
    <div v-if="!disabled" class="join">
        <label class="input input-sm join-item flex w-72 items-center gap-2">
            <KG_ICONS.adminAction.search class="size-5 opacity-50" />
            <input
                :value="value"
                type="search"
                class="grow"
                :placeholder="$t('search')"
                data-autom="input-filter-search"
                @input="update(($event.target as HTMLInputElement).value)"
            />
            <button
                v-if="value"
                type="button"
                class="btn btn-ghost btn-xs btn-square"
                :title="$t('clear_filters')"
                :aria-label="$t('clear_filters')"
                @click="clear"
            >
                <KG_ICONS.adminAction.close class="size-4" />
            </button>
            <div
                v-else-if="searchableAttributes?.length"
                class="tooltip tooltip-left"
                :data-tip="`${searchableAttributes.join(', ')}`"
            >
                <span class="opacity-50">ⓘ</span>
            </div>
        </label>
    </div>
</template>
