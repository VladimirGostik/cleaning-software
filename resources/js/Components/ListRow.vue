<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    title: string;
    description?: string;
    href?: string;
    button?: boolean;
}>();

defineSlots<{
    leading?: () => unknown;
    description?: () => unknown;
    trailing?: () => unknown;
}>();

const tag = computed(() => (props.href ? Link : props.button ? 'button' : 'div'));
const trailingTag = computed(() => (props.button ? 'span' : 'div'));
</script>

<template>
    <component
        :is="tag"
        :href="href"
        :type="button && !href ? 'button' : undefined"
        class="kg-list-row"
        :class="{ 'w-full text-left': button && !href }"
    >
        <span v-if="$slots.leading" class="kg-tile">
            <slot name="leading" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-base">{{ title }}</span>
            <span v-if="description || $slots.description" class="block text-xs text-muted">
                <slot name="description">{{ description }}</slot>
            </span>
        </span>
        <component :is="trailingTag" v-if="$slots.trailing" class="flex shrink-0 items-center gap-2">
            <slot name="trailing" />
        </component>
    </component>
</template>
