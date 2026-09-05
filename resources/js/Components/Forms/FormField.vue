<script setup lang="ts">
defineProps<{
    label: string;
    error?: string | null;
    required?: boolean;
}>();
</script>

<template>
    <fieldset class="fieldset">
        <legend class="fieldset-legend">
            {{ label }}
            <span
                v-if="required"
                class="text-error ml-0.5"
                aria-hidden="true"
            >*</span>
        </legend>
        <slot />
        <p
            v-if="error"
            class="text-error text-sm mt-1"
        >
            {{ error }}
        </p>
    </fieldset>
</template>
