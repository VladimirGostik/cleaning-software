<script setup lang="ts">
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
        <label class="input input-sm input-bordered join-item flex items-center gap-2 min-w-64">
            <span class="opacity-50">⌕</span>
            <input
                :value="value"
                type="search"
                class="grow"
                :placeholder="$t('search')"
                data-autom="input-filter-search"
                @input="update(($event.target as HTMLInputElement).value)"
            />
            <button v-if="value" type="button" class="btn btn-ghost btn-xs" @click="clear">✕</button>
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
