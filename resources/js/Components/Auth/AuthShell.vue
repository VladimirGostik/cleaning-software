<script setup lang="ts">
import BrandLogo from '@/Components/BrandLogo.vue';
import AuthLanguageSwitcher from '@/Components/Auth/AuthLanguageSwitcher.vue';

defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center bg-white px-8 py-24">
        <a href="/login" class="absolute left-8 top-6" aria-label="Kegos Clean">
            <BrandLogo :size="28" />
        </a>

        <div class="w-full max-w-[380px]">
            <div class="mb-9">
                <h1 class="text-[26px] font-semibold tracking-[-0.02em] text-base-content">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1.5 text-sm text-muted">
                    {{ subtitle }}
                </p>
            </div>

            <slot />

            <div class="mt-[18px] flex flex-col gap-[18px]">
                <div class="h-px bg-base-300" />
                <slot name="after" />
                <AuthLanguageSwitcher />
            </div>
        </div>
    </div>
</template>
