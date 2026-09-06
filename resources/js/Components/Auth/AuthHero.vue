<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { CheckIcon } from '@heroicons/vue/24/outline';

const { t } = useI18n();
</script>

<template>
    <div
        class="hidden lg:flex lg:w-[60%] flex-col justify-center px-14 pt-24 pb-16 relative overflow-hidden auth-hero-bg"
    >
        <div class="absolute inset-0 pointer-events-none auth-hero-overlay" />

        <div class="relative z-10">
            <h1 class="text-[72px] leading-[1.02] font-bold tracking-[-0.035em] text-white max-w-[640px]">
                {{ t('auth_hero_title_1') }}<br />{{ t('auth_hero_title_2') }}
            </h1>
            <p class="mt-6 text-[22px] leading-[1.5] max-w-[560px] auth-text-muted">
                {{ t('auth_hero_subtitle') }}
            </p>
            <div class="mt-11 flex gap-8 text-[16px] auth-text-bright">
                <div class="flex items-center gap-2">
                    <CheckIcon class="h-5 w-5" />
                    {{ t('auth_hero_feature_clients') }}
                </div>
                <div class="flex items-center gap-2">
                    <CheckIcon class="h-5 w-5" />
                    {{ t('auth_hero_feature_invoices') }}
                </div>
                <div class="flex items-center gap-2">
                    <CheckIcon class="h-5 w-5" />
                    {{ t('auth_hero_feature_schedule') }}
                </div>
            </div>
        </div>

        <div class="absolute bottom-10 left-14 z-10 text-[12px] auth-copyright">
            {{ t('footer_copy') }}
        </div>
    </div>
</template>
