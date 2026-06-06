<script setup lang="ts">
    import { Head, Link, router, useForm } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';
    import {
        EnvelopeIcon,
        LockClosedIcon,
        EyeIcon,
        EyeSlashIcon,
        CheckIcon,
        ArrowLeftIcon,
    } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    const props = usePageProps();
    const { t } = useTranslate();

    const canResetPassword = computed<boolean>(() => Boolean(props.canResetPassword));
    // eslint-disable-next-line no-restricted-syntax -- imperative DOM toggle: password input type attribute
    const showPassword = ref(false);
    const currentLocale = computed(() => props.locale ?? 'sk');

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit() {
        form.post('/login', {
            onFinish: () => form.reset('password'),
        });
    }

    function switchLocale(code: string) {
        router.get(`/language/${code}`, {}, { preserveState: false });
    }
</script>

<template>
    <Head :title="t('login')" />

    <div class="min-h-screen flex relative">
        <!-- Logo (positioned to match Landing nav coords) -->
        <Link
            href="/"
            class="absolute top-4 left-6 md:left-12 z-20 flex items-center gap-2 font-bold tracking-tight text-white transition hover:opacity-90"
        >
            <span
                class="flex h-7 w-7 items-center justify-center rounded-md text-white"
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
        </Link>

        <!-- Hero (left 60%) -->
        <div
            class="hidden lg:flex lg:w-[60%] flex-col justify-center px-14 pt-24 pb-16 relative overflow-hidden auth-hero-bg"
        >
            <!-- Radial overlay -->
            <div
                class="absolute inset-0 pointer-events-none"
                style="background-image: radial-gradient(circle at 80% 20%, rgba(255,255,255,.08), transparent 40%), radial-gradient(circle at 20% 80%, rgba(255,255,255,.06), transparent 40%)"
            />

            <!-- Tagline (vertically centered) -->
            <div class="relative z-10">
                <h1 class="text-[72px] leading-[1.02] font-bold tracking-[-0.035em] text-white max-w-[640px]">
                    {{ t('auth.hero.title_1') }}<br />{{ t('auth.hero.title_2') }}
                </h1>
                <p class="mt-6 text-[22px] leading-[1.5] max-w-[560px]" :style="{ color: 'var(--auth-text-muted)' }">
                    {{ t('auth.hero.subtitle') }}
                </p>
                <div class="mt-11 flex gap-8 text-[16px]" :style="{ color: 'var(--auth-text-bright)' }">
                    <div class="flex items-center gap-2">
                        <CheckIcon class="h-5 w-5" />
                        {{ t('auth.hero.feature_free') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <CheckIcon class="h-5 w-5" />
                        {{ t('auth.hero.feature_no_card') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <CheckIcon class="h-5 w-5" />
                        {{ t('auth.hero.feature_support') }}
                    </div>
                </div>
            </div>

            <!-- Copyright (absolute bottom) -->
            <div class="absolute bottom-10 left-14 z-10 text-[12px]" :style="{ color: 'var(--auth-copyright)' }">
                {{ t('landing.footer.copy') }}
            </div>
        </div>

        <!-- Form (right 40%) -->
        <div class="relative flex flex-1 flex-col items-center justify-center bg-white px-8 py-12 lg:px-16">
            <!-- Back link (top-right) -->
            <Link
                href="/"
                class="absolute right-6 top-6 inline-flex items-center gap-1.5 text-[13px] font-medium text-slate-500 transition hover:text-slate-900"
            >
                <ArrowLeftIcon class="h-3.5 w-3.5" />
                {{ t('auth.back_home') }}
            </Link>

            <!-- Mobile logo -->
            <Link href="/" class="mb-8 flex items-center gap-2 lg:hidden">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white auth-mobile-logo-bg">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M5 14c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke-linecap="round" />
                        <path d="M8 17h8" stroke-linecap="round" />
                    </svg>
                </span>
                <span class="text-lg font-bold text-slate-900">{{ t('app_name') }}</span>
            </Link>

            <div class="w-full max-w-[380px]">
                <div class="mb-9">
                    <h2 class="text-[26px] font-bold text-slate-900">{{ t('login') }}</h2>
                    <p class="mt-1.5 text-sm text-slate-500">{{ t('auth.welcome_back') }}</p>
                </div>

                <form class="flex flex-col gap-[18px]" @submit.prevent="submit">
                    <!-- Email -->
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium text-slate-700">{{ t('email') }}</label>
                        <div class="relative">
                            <EnvelopeIcon class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                                :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-100': form.errors.email }"
                            />
                        </div>
                        <p v-if="form.errors.email" class="text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <!-- Password -->
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium text-slate-700">{{ t('password') }}</label>
                        <div class="relative">
                            <LockClosedIcon class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-10 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                                :class="{ 'border-red-400 focus:border-red-400 focus:ring-red-100': form.errors.password }"
                            />
                            <button
                                type="button"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600"
                                @click="showPassword = !showPassword"
                            >
                                <EyeSlashIcon v-if="showPassword" class="h-4 w-4" />
                                <EyeIcon v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="text-xs text-red-500">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember + forgot -->
                    <div class="flex items-center justify-between">
                        <label class="flex cursor-pointer items-center gap-2">
                            <span
                                class="flex h-4 w-4 flex-shrink-0 items-center justify-center rounded border transition"
                                :class="form.remember ? 'auth-checkbox-checked' : 'auth-checkbox-unchecked'"
                            >
                                <CheckIcon v-if="form.remember" class="h-3 w-3" />
                            </span>
                            <input v-model="form.remember" type="checkbox" class="sr-only" />
                            <span class="text-[13px] text-slate-700">{{ t('remember_me') }}</span>
                        </label>
                        <Link
                            v-if="canResetPassword"
                            href="/forgot-password"
                            class="text-[13px] font-medium hover:underline auth-link"
                        >
                            {{ t('forgot_password') }}
                        </Link>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-lg py-2.5 text-sm font-semibold text-white transition auth-submit-btn disabled:opacity-70"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                        {{ t('login') }}
                    </button>

                    <!-- Divider -->
                    <div class="h-px bg-slate-200" />

                    <!-- Lang switcher -->
                    <div class="flex items-center justify-center gap-3 text-[12px] text-slate-500">
                        <template v-for="(lang, i) in (props.languages ?? [])" :key="lang.code">
                            <span v-if="i > 0" class="text-slate-300">|</span>
                            <button
                                type="button"
                                class="transition hover:text-slate-800"
                                :class="lang.code === currentLocale ? 'font-semibold text-slate-800' : ''"
                                @click="switchLocale(lang.code)"
                            >
                                {{ lang.code.toUpperCase() }}
                            </button>
                        </template>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
