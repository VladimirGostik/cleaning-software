<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    subject: App.Data.Tasks.TaskSubjectData;
}>();
</script>

<template>
    <Link v-if="subject.url" :href="subject.url" class="link link-hover">{{ subject.label }}</Link>
    <span v-else>{{ subject.label }}</span>
</template>
