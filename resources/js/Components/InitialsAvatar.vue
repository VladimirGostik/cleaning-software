<script setup lang="ts">
import { computed } from 'vue';
import { initials } from '@/utils/text';

const props = withDefaults(
    defineProps<{
        name: string;
        size?: 'sm' | 'md';
    }>(),
    { size: 'sm' },
);

const letters = computed(() => initials(props.name));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full bg-base-200 font-medium text-base-content"
        :class="size === 'md' ? 'size-9 text-xs' : 'size-6 text-2xs'"
        :title="name"
        :aria-label="name"
        role="img"
    >
        {{ letters }}
    </span>
</template>
