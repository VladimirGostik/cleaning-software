<script setup lang="ts">
    import { computed } from 'vue';
    import { useAuthorization } from '@/Composables/useAuthorization';

    /**
     * <Can> — declarative capability gate component.
     *
     * Renders the default slot when the AND of all provided checks passes.
     * Falls back to #fallback slot (if provided) when the check fails.
     * Renders nothing when fallback slot is absent and check fails.
     *
     * Rules:
     *  - Both props absent   → always renders (no restriction).
     *  - permission only     → checks permission axis.
     *  - feature only        → checks feature/plan axis.
     *  - Both present        → AND (both must pass).
     *  - Denial copy goes in the caller's #fallback slot, not here.
     */

    const props = defineProps<{
        permission?: App.Enums.PermissionEnum;
        feature?: App.Enums.FeatureEnum;
    }>();

    defineSlots<{
        default(): unknown;
        fallback(): unknown;
    }>();

    const { can, hasFeature } = useAuthorization();

    const allowed = computed<boolean>(
        () =>
            (props.permission === undefined || can(props.permission)) &&
            (props.feature === undefined || hasFeature(props.feature)),
    );
</script>

<template>
    <slot v-if="allowed" />
    <slot v-else name="fallback" />
</template>
