<script setup lang="ts">
import BrandLogo from '@/Components/BrandLogo.vue';
import AuthLanguageSwitcher from '@/Components/Auth/AuthLanguageSwitcher.vue';

defineProps<{
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center bg-base-200 px-5 py-24 lg:px-6">
        <a href="/login" class="absolute left-5 top-6 lg:left-6" aria-label="Kegos Clean">
            <BrandLogo :size="28" />
        </a>

        <div class="card w-full max-w-sm bg-base-100">
            <div class="card-body">
                <div class="mb-6">
                    <h1 class="text-2xl font-normal text-base-content">{{ title }}</h1>
                    <p v-if="subtitle" class="mt-1 text-sm text-muted">
                        {{ subtitle }}
                    </p>
                </div>

                <slot />

                <div class="mt-6 flex flex-col gap-4">
                    <slot name="after" />
                    <AuthLanguageSwitcher />
                </div>
            </div>
        </div>
    </div>
</template>
