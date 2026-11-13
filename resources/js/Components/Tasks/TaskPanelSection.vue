<script setup lang="ts">
import { useId } from 'vue';

defineProps<{ title: string }>();

const headingId = useId();
</script>

<template>
    <section class="space-y-2" :aria-labelledby="headingId">
        <div class="flex min-h-(--kg-row-height) items-center gap-2">
            <h2 :id="headingId" class="text-sm font-medium" :class="{ 'sr-only': $slots.title }">{{ title }}</h2>
            <slot name="title" />
            <slot name="meta" />
            <div class="ms-auto flex shrink-0 items-center gap-1">
                <slot name="actions" />
            </div>
        </div>
        <slot />
    </section>
</template>
