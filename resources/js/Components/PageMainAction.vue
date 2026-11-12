<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AddIcon from '~icons/material-symbols/add-outline-rounded';

const props = defineProps<{
    label: string;
    href?: string;
}>();

const emit = defineEmits<{
    click: [];
}>();

const tag = computed(() => (props.href ? Link : 'button'));
</script>

<template>
    <component
        :is="tag"
        :href="href"
        :type="href ? undefined : 'button'"
        class="btn btn-secondary kg-main-action"
        @click="emit('click')"
    >
        <AddIcon aria-hidden="true" />
        <span class="kg-main-action__label">{{ label }}</span>
    </component>
</template>
