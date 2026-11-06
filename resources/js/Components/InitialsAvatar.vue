<script setup lang="ts">
import { computed } from 'vue';
import { initials } from '@/utils/text';

const props = defineProps<{
    name: string;
}>();

const letters = computed(() => initials(props.name));
</script>

<template>
    <span
        class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-base-200 text-xs font-medium text-base-content"
        :title="name"
        :aria-label="name"
        role="img"
    >
        {{ letters }}
    </span>
</template>
