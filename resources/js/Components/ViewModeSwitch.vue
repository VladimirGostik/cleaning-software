<script setup lang="ts">
import type { Component } from 'vue';

interface ViewModeOption {
    value: string;
    label: string;
    icon: Component;
}

defineProps<{
    modelValue: string;
    options: ViewModeOption[];
    label: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div role="group" :aria-label="label" class="tabs tabs-box w-fit">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            class="tab gap-1"
            :class="{ 'tab-active': modelValue === option.value }"
            :aria-pressed="modelValue === option.value"
            @click="emit('update:modelValue', option.value)"
        >
            <component :is="option.icon" aria-hidden="true" />
            {{ option.label }}
        </button>
    </div>
</template>
