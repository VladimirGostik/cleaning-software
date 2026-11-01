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
        <component :is="icon" v-if="icon" class="mx-auto size-5 text-muted" aria-hidden="true" />
        <h3 class="mt-3 font-semibold">{{ title }}</h3>
        <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
        <div v-if="$slots.cta" class="mt-4">
            <slot name="cta" />
        </div>
    </div>
</template>
