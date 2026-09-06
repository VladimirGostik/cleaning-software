<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import BrandMark from '@/Components/BrandMark.vue';
import AuthHero from '@/Components/Auth/AuthHero.vue';
import AuthLanguageSwitcher from '@/Components/Auth/AuthLanguageSwitcher.vue';

const { t } = useI18n();

defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <div class="min-h-screen flex relative">
        <a
            href="/login"
            class="absolute top-4 left-6 md:left-12 z-20 flex items-center gap-2 font-bold tracking-tight text-white transition hover:opacity-90"
        >
            <span class="flex h-7 w-7 items-center justify-center rounded-md text-white auth-logo-chip">
                <BrandMark class="h-4 w-4" />
            </span>
            <span class="text-[17px]">{{ t('app_name') }}</span>
        </a>

        <AuthHero />

        <div class="relative flex flex-1 flex-col items-center justify-center bg-white px-8 py-12 lg:px-16">
            <a href="/login" class="mb-8 flex items-center gap-2 lg:hidden">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white auth-mobile-logo-bg">
                    <BrandMark class="h-4 w-4" />
                </span>
                <span class="text-lg font-bold text-slate-900">{{ t('app_name') }}</span>
            </a>

            <div class="w-full max-w-[380px]">
                <div class="mb-9">
                    <h2 class="text-[26px] font-bold text-slate-900">{{ title }}</h2>
                    <p v-if="subtitle" class="mt-1.5 text-sm text-slate-500">
                        {{ subtitle }}
                    </p>
                </div>

                <slot />

                <div class="mt-[18px] flex flex-col gap-[18px]">
                    <div class="h-px bg-slate-200" />
                    <slot name="after" />
                    <AuthLanguageSwitcher />
                </div>
            </div>
        </div>
    </div>
</template>
