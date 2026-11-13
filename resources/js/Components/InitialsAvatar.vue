<script setup lang="ts">
import { computed } from 'vue';
import { AVATAR_TONE_CLASSES, avatarTone } from '@/utils/avatarTone';
import { initials } from '@/utils/text';

const props = withDefaults(
    defineProps<{
        name: string;
        size?: 'sm' | 'md';
        decorative?: boolean;
    }>(),
    { size: 'sm', decorative: false },
);

const letters = computed(() => initials(props.name));
const tone = computed(() => AVATAR_TONE_CLASSES[avatarTone(props.name)]);
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full font-medium"
        :class="[tone, size === 'md' ? 'size-9 text-xs' : 'size-6 text-2xs']"
        :title="decorative ? undefined : name"
        :aria-label="decorative ? undefined : name"
        :role="decorative ? undefined : 'img'"
        :aria-hidden="decorative ? 'true' : undefined"
    >
        {{ letters }}
    </span>
</template>
