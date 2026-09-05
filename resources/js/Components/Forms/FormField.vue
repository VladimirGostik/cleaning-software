<script setup lang="ts">
    withDefaults(
        defineProps<{
            label?: string;
            error?: string;
            required?: boolean;
        }>(),
        {
            label: undefined,
            error: undefined,
            required: false,
        },
    );
</script>

<template>
    <fieldset class="fieldset w-full">
        <legend v-if="label" class="fieldset-legend">
            {{ label }}
            <span v-if="required" class="text-error" aria-hidden="true">*</span>
        </legend>
        <slot />
        <span v-if="error" class="fieldset-label text-error" role="alert">{{ error }}</span>
    </fieldset>
</template>
