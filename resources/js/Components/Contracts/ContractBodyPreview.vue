<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    body: string;
}>();

const TOKEN_SPLIT_RE = /(\{\{[a-z_]+(?:\.[a-z_]+)*\}\})/g;
const TOKEN_RE = /^\{\{[a-z_]+(?:\.[a-z_]+)*\}\}$/;

interface Segment {
    text: string;
    isToken: boolean;
}

const segments = computed<Segment[]>(() =>
    props.body.split(TOKEN_SPLIT_RE).map((text) => ({ text, isToken: TOKEN_RE.test(text) })),
);
</script>

<template>
    <p class="whitespace-pre-wrap text-sm leading-relaxed">
        <template v-for="(segment, index) in segments" :key="index">
            <code v-if="segment.isToken" class="rounded bg-warning/20 px-1 font-mono text-xs">{{ segment.text }}</code>
            <span v-else>{{ segment.text }}</span>
        </template>
    </p>
</template>
