<script setup lang="ts">
export interface ToolbarSelectOption {
    value: string;
    label: string;
    disabled?: boolean;
}

defineProps<{
    label: string;
    options: ToolbarSelectOption[];
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <select v-model="model" class="select select-xs w-auto" :aria-label="label" :title="label">
        <option v-for="option in options" :key="option.value" :value="option.value" :disabled="option.disabled">
            {{ option.label }}
        </option>
    </select>
</template>
