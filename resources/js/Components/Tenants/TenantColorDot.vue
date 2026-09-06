<script setup lang="ts">
defineProps<{ color: App.Enums.TenantColorEnum | null }>();
</script>

<template>
    <span
        class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
        :class="{ 'bg-primary': color === null }"
        :style="color ? { backgroundColor: color } : undefined"
        aria-hidden="true"
    />
</template>
