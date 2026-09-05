<script setup lang="ts">
    import { computed } from 'vue';
    import { useAuthorization } from '@/Composables/useAuthorization';

    /**
     * <Can> — declarative capability gate component.
     *
     * Renders the default slot when the permission check passes.
     * Falls back to #fallback slot (if provided) when the check fails.
     * Renders nothing when fallback slot is absent and check fails.
     *
     * Rules:
     *  - permission absent → always renders (no restriction).
     *  - permission present → checks permission axis.
     *  - Denial copy goes in the caller's #fallback slot, not here.
     */

    const props = defineProps<{
        permission?: App.Enums.PermissionEnum;
    }>();

    defineSlots<{
        default(): unknown;
        fallback(): unknown;
    }>();

    const { can } = useAuthorization();

    const allowed = computed<boolean>(() => props.permission === undefined || can(props.permission));
</script>

<template>
    <slot v-if="allowed" />
    <slot v-else name="fallback" />
</template>
