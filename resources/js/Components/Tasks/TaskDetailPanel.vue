<script setup lang="ts">
import SideDrawer from '@/Components/SideDrawer.vue';

defineProps<{
    heading: string;
}>();

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <SideDrawer open :modal="false" :title="heading" @close="emit('close')">
        <slot />
    </SideDrawer>
</template>
