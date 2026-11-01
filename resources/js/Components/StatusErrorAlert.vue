<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const message = computed(() => (page.props.errors as Record<string, string | undefined>).status);
</script>

<template>
    <div v-if="message" class="alert alert-error" role="alert">
        <span>{{ message }}</span>
    </div>
</template>
