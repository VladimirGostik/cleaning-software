<script setup lang="ts">
import type { InertiaForm } from './useFormContext';
import { provideFormContext } from './useFormContext';

const props = defineProps<{
    form: InertiaForm;
}>();

provideFormContext(props.form);
</script>

<template>
    <slot />
</template>
