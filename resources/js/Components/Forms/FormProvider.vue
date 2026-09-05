<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { provideForm } from './useFormContext';

const props = defineProps<{
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    form: ReturnType<typeof useForm<any>>;
}>();

provideForm(props.form);
</script>

<template>
    <slot />
</template>
