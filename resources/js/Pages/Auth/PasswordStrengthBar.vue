<script setup lang="ts">
    import { computed } from 'vue';
    import { useTranslate } from '@/Composables/useTranslate';

    const props = defineProps<{
        password: string;
    }>();

    const { t } = useTranslate();

    const score = computed<number>(() => {
        const p = props.password;
        if (!p) {
            return 0;
        }
        let s = 0;
        if (p.length >= 8) {
            s++;
        }
        if (/[a-z]/.test(p) && /[A-Z]/.test(p)) {
            s++;
        }
        if (/\d/.test(p)) {
            s++;
        }
        if (/[^a-zA-Z0-9]/.test(p)) {
            s++;
        }
        return s;
    });

    const widthPercent = computed<number>(() => (score.value / 4) * 100);

    const labelKey = computed<string>(() => {
        if (score.value <= 1) {
            return 'register.password.weak';
        }
        if (score.value === 2) {
            return 'register.password.fair';
        }
        if (score.value === 3) {
            return 'register.password.good';
        }
        return 'register.password.strong';
    });

    const barClass = computed<string>(() => {
        if (score.value <= 1) {
            return 'bg-red-500';
        }
        if (score.value === 2) {
            return 'bg-amber-400';
        }
        if (score.value === 3) {
            return 'bg-amber-500';
        }
        return 'bg-green-500';
    });

    const textClass = computed<string>(() => {
        if (score.value <= 1) {
            return 'text-red-500';
        }
        if (score.value === 2) {
            return 'text-amber-500';
        }
        if (score.value === 3) {
            return 'text-amber-600';
        }
        return 'text-green-600';
    });
</script>

<template>
    <div v-if="password.length > 0" class="flex flex-col gap-1">
        <div
            class="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden"
            role="progressbar"
            :aria-valuenow="score"
            aria-valuemin="0"
            aria-valuemax="4"
            :aria-label="t(labelKey)"
        >
            <div
                class="h-full rounded-full transition-all duration-300"
                :class="barClass"
                :style="{ width: `${widthPercent}%` }"
            />
        </div>
        <span class="text-[11px] font-medium" :class="textClass">{{ t(labelKey) }}</span>
    </div>
</template>
