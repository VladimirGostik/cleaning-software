<script setup lang="ts">
defineProps<{ color: App.Enums.TenantColorEnum | null }>();
</script>

<template>
    <span
        class="kg-dot"
        :class="{ 'bg-primary': color === null }"
        :style="color ? { backgroundColor: color } : undefined"
        aria-hidden="true"
    />
</template>
