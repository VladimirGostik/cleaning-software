<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const languages = computed(() => page.props.languages);
const locale = computed(() => page.props.locale);
</script>

<template>
    <div class="flex items-center justify-center gap-3 text-[12px] text-slate-500">
        <template v-for="(lang, i) in languages" :key="lang.value">
            <span v-if="i > 0" class="text-slate-300" aria-hidden="true">|</span>
            <a
                :href="`/language/${lang.value}`"
                class="transition hover:text-slate-800"
                :class="lang.value === locale ? 'font-semibold text-slate-800' : ''"
                :aria-current="lang.value === locale ? 'true' : undefined"
                :lang="lang.value"
            >
                {{ lang.value.toUpperCase() }}
            </a>
        </template>
    </div>
</template>
