<script setup lang="ts">
import type { StatusTone } from './statusTone';

withDefaults(
    defineProps<{
        tone: StatusTone;
        label: string;
        size?: 'sm' | 'md';
    }>(),
    { size: 'md' },
);
</script>

<template>
    <span class="kg-chip" :class="[`kg-chip--${tone}`, { 'kg-chip--sm': size === 'sm' }]">{{ label }}</span>
</template>
