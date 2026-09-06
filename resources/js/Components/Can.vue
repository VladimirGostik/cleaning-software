<script setup lang="ts">
import { computed } from 'vue';
import { useAuthorization } from '@/Composables/useAuthorization';

const props = defineProps<{ permission: App.Enums.PermissionEnum }>();

defineSlots<{
    default(): unknown;
    fallback(): unknown;
}>();

const { allows } = useAuthorization();

const allowed = computed(() => allows(props.permission));
</script>

<template>
    <slot v-if="allowed" />
    <slot v-else name="fallback" />
</template>
