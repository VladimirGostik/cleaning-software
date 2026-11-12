<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();

defineSlots<{ cta?: () => unknown }>();
</script>

<template>
    <div class="py-10 text-center">
        <span v-if="icon" class="kg-tile kg-tile--lg">
            <component :is="icon" class="size-5.5 text-muted" aria-hidden="true" />
        </span>
        <h3 class="mt-3 text-base font-medium">{{ title }}</h3>
        <p v-if="description" class="mt-1 text-xs text-muted">{{ description }}</p>
        <div v-if="$slots.cta" class="mt-4">
            <slot name="cta" />
        </div>
    </div>
</template>
