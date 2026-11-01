<script setup lang="ts">
import { useFieldError } from './useFieldError';
import { useFormContext } from './useFormContext';

const props = defineProps<{ field: string }>();

const error = useFieldError(props, useFormContext());
</script>

<template>
    <p v-if="error" class="text-error text-sm mt-1">
        {{ error }}
    </p>
</template>
