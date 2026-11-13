<script setup lang="ts">
defineProps<{
    label: string;
}>();
</script>

<template>
    <div
        class="grid min-h-(--kg-row-height) grid-cols-[var(--kg-property-label-width)_minmax(0,1fr)] items-center gap-3"
    >
        <dt class="text-muted">{{ label }}</dt>
        <dd class="min-w-0">
            <slot />
        </dd>
    </div>
</template>
