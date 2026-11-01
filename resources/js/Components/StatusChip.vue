<script setup lang="ts">
import { computed, type Component } from 'vue';
import { TONE_ICON, type StatusTone } from './statusTone';

const props = withDefaults(
    defineProps<{
        tone: StatusTone;
        label: string;
        icon?: Component;
        size?: 'sm' | 'md';
    }>(),
    { icon: undefined, size: 'md' },
);

const resolvedIcon = computed(() => props.icon ?? TONE_ICON[props.tone]);
</script>

<template>
    <span class="kg-chip" :class="[`kg-chip--${tone}`, { 'kg-chip--sm': size === 'sm' }]" role="status">
        <component :is="resolvedIcon" v-if="resolvedIcon" aria-hidden="true" />
        {{ label }}
    </span>
</template>
