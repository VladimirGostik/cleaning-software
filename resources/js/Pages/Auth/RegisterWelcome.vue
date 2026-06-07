<script setup lang="ts">
    import { Link } from '@inertiajs/vue3';
    import { UsersIcon, BuildingOfficeIcon, Cog6ToothIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';

    defineProps<{
        companyName: string;
    }>();

    const emit = defineEmits<{
        (e: 'continue'): void;
    }>();

    const { t } = useTranslate();

    const quickStartCards = [
        {
            key: 'clients',
            icon: UsersIcon,
            titleKey: 'register.quick_clients',
            descKey: 'register.quick_clients_desc',
            href: '/clients',
        },
        {
            key: 'objects',
            icon: BuildingOfficeIcon,
            titleKey: 'register.quick_object',
            descKey: 'register.quick_object_desc',
            href: '/dashboard',
        },
        {
            key: 'settings',
            icon: Cog6ToothIcon,
            titleKey: 'register.quick_settings',
            descKey: 'register.quick_settings_desc',
            href: '/dashboard',
        },
    ] as const;
</script>

<template>
    <div class="flex flex-col items-center justify-center min-h-screen px-6 py-16 auth-hero-bg relative overflow-hidden">
        <!-- Radial overlay -->
        <div
            class="absolute inset-0 pointer-events-none"
            style="background-image: radial-gradient(circle at 80% 20%, rgba(255,255,255,.08), transparent 40%), radial-gradient(circle at 20% 80%, rgba(255,255,255,.06), transparent 40%)"
        />

        <div class="relative z-10 w-full max-w-lg text-center">
            <!-- Check icon -->
            <div
                class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full"
                style="background: rgba(255,255,255,0.15); backdrop-filter: blur(8px)"
            >
                <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h1 class="text-[32px] font-bold text-white tracking-tight">{{ t('register.welcome_title') }}</h1>
            <p class="mt-3 text-[16px]" :style="{ color: 'var(--auth-text-bright)' }">
                {{ companyName }} — {{ t('register.welcome_subtitle') }}
            </p>

            <!-- Quick-start cards -->
            <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <Link
                    v-for="card in quickStartCards"
                    :key="card.key"
                    :href="card.href"
                    class="flex flex-col items-center gap-3 rounded-xl p-5 text-center transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-white/50"
                    style="background: rgba(255,255,255,0.12); backdrop-filter: blur(8px)"
                >
                    <component :is="card.icon" class="h-7 w-7 text-white" />
                    <span class="text-sm font-semibold text-white">{{ t(card.titleKey) }}</span>
                    <span class="text-xs" :style="{ color: 'var(--auth-text-bright)' }">{{ t(card.descKey) }}</span>
                </Link>
            </div>

            <!-- Continue button -->
            <button
                type="button"
                class="mt-10 rounded-lg px-8 py-2.5 text-sm font-semibold transition auth-submit-btn"
                @click="emit('continue')"
            >
                {{ t('dashboard') }} →
            </button>
        </div>
    </div>
</template>
