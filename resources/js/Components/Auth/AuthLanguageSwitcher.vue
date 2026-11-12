<script setup lang="ts">
import { computed } from 'vue';
import { usePageProps } from '@/Composables/usePageProps';

const props = usePageProps();

const languages = computed(() => props.value.languages);
const locale = computed(() => props.value.locale);
</script>

<template>
    <div class="flex items-center justify-center gap-2 text-xs text-muted">
        <template v-for="lang in languages" :key="lang.value">
            <a
                :href="`/language/${lang.value}`"
                class="inline-flex min-h-11 items-center px-2 transition hover:text-base-content"
                :class="lang.value === locale ? 'font-medium text-base-content' : ''"
                :aria-current="lang.value === locale ? 'true' : undefined"
                :lang="lang.value"
            >
                {{ lang.value.toUpperCase() }}
            </a>
        </template>
    </div>
</template>
