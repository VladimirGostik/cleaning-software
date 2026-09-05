<script setup lang="ts">
    import { Head, Link, router, useForm } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';
    import {
        EnvelopeIcon,
        LockClosedIcon,
        EyeIcon,
        EyeSlashIcon,
        ArrowLeftIcon,
        UserIcon,
    } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import PasswordStrengthBar from '@/Pages/Auth/PasswordStrengthBar.vue';

    type AcceptState = 'existing_user' | 'new_user' | 'wrong_user' | 'expired' | 'invalid';

    const props = defineProps<{
        state: AcceptState;
        token: string;
        email: string | null;
        tenantName: string | null;
        roleName: string | null;
        invitedEmail: string | null;
    }>();

    const pageProps = usePageProps();
    const { t } = useTranslate();

    const currentLocale = computed(() => pageProps.locale ?? 'sk');

    // eslint-disable-next-line no-restricted-syntax -- imperative DOM toggle: password input type attribute
    const showPassword = ref(false);

    const form = useForm<{ name: string; password: string }>({ name: '', password: '' });

    const isFormState = computed(() => props.state === 'existing_user' || props.state === 'new_user');

    function submit() {
        form.post(`/invitations/${props.token}`, {
            onFinish: () => form.reset('password'),
        });
    }

    function logout() {
        router.post('/logout');
    }

    function switchLocale(code: string) {
        router.get(`/language/${code}`, {}, { preserveState: false });
    }
</script>

<template>
    <Head :title="t('invitation.accept_title')" />

    <!-- Error / block states: single-pane centered card -->
    <template v-if="state === 'expired' || state === 'invalid' || state === 'wrong_user'">
        <div class="min-h-screen flex items-center justify-center bg-slate-50 px-4">
            <div
                class="w-full max-w-[420px] rounded-2xl bg-white shadow-sm border border-slate-200 p-10 text-center"
            >
                <!-- Mobile logo -->
                <Link href="/" class="inline-flex items-center gap-2 mb-8">
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-white auth-mobile-logo-bg"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path d="M5 14c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke-linecap="round" />
                            <path d="M8 17h8" stroke-linecap="round" />
                        </svg>
                    </span>
                    <span class="text-lg font-bold text-slate-900">{{ t('app_name') }}</span>
                </Link>

                <!-- wrong_user -->
                <template v-if="state === 'wrong_user'">
                    <h1 class="text-[22px] font-bold text-slate-900 mb-3">
                        {{ t('invitation.accept_title') }}
                    </h1>
                    <p class="text-sm text-slate-600 mb-6">
                        {{ t('invitation.wrong_user_block') }}
                        <span v-if="invitedEmail" class="font-medium text-slate-900">{{ invitedEmail }}</span>
                    </p>
                    <button
                        type="button"
                        class="flex w-full items-center justify-center rounded-lg py-2.5 text-sm font-semibold text-white transition auth-submit-btn"
                        @click="logout"
                    >
                        {{ t('invitation.logout_to_accept') }}
                    </button>
                    <Link
                        href="/login"
                        class="mt-4 inline-block text-sm font-medium hover:underline auth-link"
                    >
                        <ArrowLeftIcon class="inline h-3.5 w-3.5 mr-1" />
                        {{ t('invitation.back_to_login') }}
                    </Link>
                </template>

                <!-- expired -->
                <template v-else-if="state === 'expired'">
                    <h1 class="text-[22px] font-bold text-slate-900 mb-3">
                        {{ t('invitation.accept_title') }}
                    </h1>
                    <p class="text-sm text-slate-600 mb-6">{{ t('invitation.expired') }}</p>
                    <Link
                        href="/login"
                        class="inline-flex items-center gap-1.5 text-sm font-medium hover:underline auth-link"
                    >
                        <ArrowLeftIcon class="h-3.5 w-3.5" />
                        {{ t('invitation.back_to_login') }}
                    </Link>
                </template>

                <!-- invalid -->
                <template v-else-if="state === 'invalid'">
                    <h1 class="text-[22px] font-bold text-slate-900 mb-3">
                        {{ t('invitation.accept_title') }}
                    </h1>
                    <p class="text-sm text-slate-600 mb-6">{{ t('invitation.invalid') }}</p>
                    <Link
                        href="/login"
                        class="inline-flex items-center gap-1.5 text-sm font-medium hover:underline auth-link"
                    >
                        <ArrowLeftIcon class="h-3.5 w-3.5" />
                        {{ t('invitation.back_to_login') }}
                    </Link>
                </template>
            </div>
        </div>
    </template>

    <!-- Form states: two-pane hero + white form panel -->
    <template v-if="isFormState">
        <div class="min-h-screen flex relative">
            <!-- Logo (hero pane absolute) -->
            <Link
                href="/"
                class="absolute top-4 left-6 md:left-12 z-20 flex items-center gap-2 font-bold tracking-tight text-white transition hover:opacity-90"
            >
                <span
                    class="flex h-7 w-7 items-center justify-center rounded-md text-white"
                    style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(8px)"
                >
                    <svg
                        viewBox="0 0 24 24"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
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
                    style="
                        background-image:
                            radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.08), transparent 40%),
                            radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.06), transparent 40%);
                    "
                />

                <div class="relative z-10">
                    <h1
                        class="text-[72px] leading-[1.02] font-bold tracking-[-0.035em] text-white max-w-[640px]"
                    >
                        {{ t('auth.hero.title_1') }}<br />{{ t('auth.hero.title_2') }}
                    </h1>
                    <p
                        class="mt-6 text-[22px] leading-[1.5] max-w-[560px]"
                        :style="{ color: 'var(--auth-text-muted)' }"
                    >
                        {{ t('auth.hero.subtitle') }}
                    </p>
                </div>

                <div
                    class="absolute bottom-10 left-14 z-10 text-[12px]"
                    :style="{ color: 'var(--auth-copyright)' }"
                >
                    {{ t('landing.footer.copy') }}
                </div>
            </div>

            <!-- Form panel (right 40%) -->
            <div
                class="relative flex flex-1 flex-col items-center justify-center bg-white px-8 py-12 lg:px-16"
            >
                <!-- Back link -->
                <Link
                    href="/"
                    class="absolute right-6 top-6 inline-flex items-center gap-1.5 text-[13px] font-medium text-slate-500 transition hover:text-slate-900"
                >
                    <ArrowLeftIcon class="h-3.5 w-3.5" />
                    {{ t('auth.back_home') }}
                </Link>

                <!-- Mobile logo -->
                <Link href="/" class="mb-8 flex items-center gap-2 lg:hidden">
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-white auth-mobile-logo-bg"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path d="M5 14c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke-linecap="round" />
                            <path d="M8 17h8" stroke-linecap="round" />
                        </svg>
                    </span>
                    <span class="text-lg font-bold text-slate-900">{{ t('app_name') }}</span>
                </Link>

                <div class="w-full max-w-[380px]">
                    <div class="mb-9">
                        <h2 class="text-[26px] font-bold text-slate-900">
                            {{ t('invitation.accept_title') }}
                        </h2>
                        <p class="mt-1.5 text-sm text-slate-500">
                            <template v-if="state === 'existing_user'">
                                {{ t('invitation.existing_user_hint') }}
                            </template>
                            <template v-else>
                                {{ t('invitation.new_user_hint') }}
                            </template>
                        </p>
                        <p v-if="tenantName || roleName" class="mt-1 text-sm font-medium text-slate-700">
                            {{ tenantName }}<template v-if="tenantName && roleName"> — </template
                            >{{ roleName }}
                        </p>
                    </div>

                    <form class="flex flex-col gap-[18px]" novalidate @submit.prevent="submit">
                        <!-- Email (immutable display) -->
                        <div class="flex flex-col gap-1.5">
                            <label for="accept-email" class="text-sm font-medium text-slate-700">
                                {{ t('invitation.email_label') }}
                            </label>
                            <div class="relative">
                                <EnvelopeIcon
                                    class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    id="accept-email"
                                    :value="email"
                                    type="email"
                                    disabled
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-500 outline-none auth-input opacity-75 cursor-not-allowed"
                                />
                            </div>
                        </div>

                        <!-- Name (new_user only) -->
                        <div v-if="state === 'new_user'" class="flex flex-col gap-1.5">
                            <label for="accept-name" class="text-sm font-medium text-slate-700">
                                {{ t('invitation.name_label') }}
                            </label>
                            <div class="relative">
                                <UserIcon
                                    class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    id="accept-name"
                                    v-model="form.name"
                                    type="text"
                                    autocomplete="name"
                                    class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                                    :class="{
                                        'border-red-400 focus:border-red-400 focus:ring-red-100':
                                            form.errors.name,
                                    }"
                                />
                            </div>
                            <p v-if="form.errors.name" class="text-xs text-red-500">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Password -->
                        <div class="flex flex-col gap-1.5">
                            <label for="accept-password" class="text-sm font-medium text-slate-700">
                                {{ t('invitation.password_label') }}
                            </label>
                            <div class="relative">
                                <LockClosedIcon
                                    class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    id="accept-password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    :autocomplete="state === 'new_user' ? 'new-password' : 'current-password'"
                                    class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-10 text-sm text-slate-900 placeholder-slate-400 outline-none transition auth-input"
                                    :class="{
                                        'border-red-400 focus:border-red-400 focus:ring-red-100':
                                            form.errors.password,
                                    }"
                                />
                                <button
                                    type="button"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600"
                                    :aria-label="
                                        showPassword
                                            ? t('auth.toggle_password_hide')
                                            : t('auth.toggle_password_show')
                                    "
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeSlashIcon v-if="showPassword" class="h-4 w-4" />
                                    <EyeIcon v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <PasswordStrengthBar v-if="state === 'new_user'" :password="form.password" />
                            <p v-if="form.errors.password" class="text-xs text-red-500">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Submit -->
                        <button
                            type="submit"
                            class="flex w-full items-center justify-center rounded-lg py-2.5 text-sm font-semibold text-white transition auth-submit-btn disabled:opacity-70"
                            :disabled="form.processing"
                        >
                            <span
                                v-if="form.processing"
                                class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                            />
                            {{ t('invitation.accept_cta') }}
                        </button>

                        <!-- Divider -->
                        <div class="h-px bg-slate-200" />

                        <!-- Lang switcher -->
                        <div class="flex items-center justify-center gap-3 text-[12px] text-slate-500">
                            <template v-for="(lang, i) in pageProps.languages ?? []" :key="lang.code">
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
</template>
