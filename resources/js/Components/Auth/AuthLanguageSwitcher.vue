<script setup lang="ts">
import { computed } from 'vue';
import { usePageProps } from '@/Composables/usePageProps';

const props = usePageProps();

const languages = computed(() => props.value.languages);
const locale = computed(() => props.value.locale);
</script>

<template>
    <div class="flex items-center justify-center gap-3 text-xs text-muted">
        <template v-for="(lang, i) in languages" :key="lang.value">
            <span v-if="i > 0" class="text-base-300" aria-hidden="true">|</span>
            <a
                :href="`/language/${lang.value}`"
                class="transition hover:text-base-content"
                :class="lang.value === locale ? 'font-semibold text-base-content' : ''"
                :aria-current="lang.value === locale ? 'true' : undefined"
                :lang="lang.value"
            >
                {{ lang.value.toUpperCase() }}
            </a>
        </template>
    </div>
</template>
