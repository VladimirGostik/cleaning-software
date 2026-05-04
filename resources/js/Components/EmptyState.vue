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
    <div class="text-center py-10">
        <component :is="icon" v-if="icon" class="w-12 h-12 mx-auto text-base-content/30" />
        <h3 class="mt-3 font-semibold">{{ title }}</h3>
        <p v-if="description" class="text-sm text-base-content/60 mt-1">{{ description }}</p>
        <div v-if="$slots.cta" class="mt-4">
            <slot name="cta" />
        </div>
    </div>
</template>
