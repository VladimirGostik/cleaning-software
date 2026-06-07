<script setup lang="ts">
    import { CheckIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';

    defineProps<{
        currentStep: number;
    }>();

    const { t } = useTranslate();

    const benefits = [
        'register.benefit_1',
        'register.benefit_2',
        'register.benefit_3',
        'register.benefit_4',
    ] as const;

    const steps = [1, 2, 3] as const;
</script>

<template>
    <div
        class="hidden lg:flex lg:w-[40%] flex-col justify-center px-14 pt-24 pb-16 relative overflow-hidden auth-hero-bg"
    >
        <!-- Radial overlay -->
        <div
            class="absolute inset-0 pointer-events-none"
            style="background-image: radial-gradient(circle at 80% 20%, rgba(255,255,255,.08), transparent 40%), radial-gradient(circle at 20% 80%, rgba(255,255,255,.06), transparent 40%)"
        />

        <div class="relative z-10">
            <!-- Logo wordmark -->
            <div class="mb-10 flex items-center gap-2 font-bold tracking-tight text-white">
                <span
                    class="flex h-7 w-7 items-center justify-center rounded-md"
                    style="background: rgba(255,255,255,0.15); backdrop-filter: blur(8px)"
                >
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M5 14c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke-linecap="round" />
                        <path d="M8 17h8" stroke-linecap="round" />
                        <circle cx="17" cy="6" r="1" fill="currentColor" />
                        <circle cx="13" cy="4" r="0.7" fill="currentColor" />
                    </svg>
                </span>
                <span class="text-[17px]">{{ t('app_name') }}</span>
            </div>

            <!-- Tagline -->
            <h1 class="text-[48px] leading-[1.05] font-bold tracking-[-0.03em] text-white max-w-[400px]">
                {{ t('auth.hero.title_1') }}<br />{{ t('auth.hero.title_2') }}
            </h1>

            <!-- Benefits -->
            <ul class="mt-8 flex flex-col gap-3">
                <li
                    v-for="key in benefits"
                    :key="key"
                    class="flex items-center gap-2.5 text-[15px]"
                    :style="{ color: 'var(--auth-text-bright)' }"
                >
                    <CheckIcon class="h-4 w-4 flex-shrink-0" />
                    {{ t(key) }}
                </li>
            </ul>

            <!-- Step dots -->
            <div class="mt-12 flex items-center gap-2.5">
                <div
                    v-for="s in steps"
                    :key="s"
                    class="h-2.5 rounded-full transition-all duration-300"
                    :class="
                        s === currentStep
                            ? 'w-6 bg-white'
                            : s < currentStep
                              ? 'w-2.5 bg-white/60'
                              : 'w-2.5 bg-white/30'
                    "
                />
            </div>
        </div>

        <!-- Copyright -->
        <div class="absolute bottom-10 left-14 z-10 text-[12px]" :style="{ color: 'var(--auth-copyright)' }">
            {{ t('landing.footer.copy') }}
        </div>
    </div>
</template>
