<script setup lang="ts">
    import { computed, reactive } from 'vue';
    import { Head, Link, router, useForm } from '@inertiajs/vue3';
    import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useIcoLookup } from '@/Composables/useIcoLookup';
    import RegisterHero from '@/Pages/Auth/RegisterHero.vue';
    import RegisterStepAccount from '@/Pages/Auth/RegisterStepAccount.vue';
    import RegisterStepCompany from '@/Pages/Auth/RegisterStepCompany.vue';
    import RegisterStepInvites from '@/Pages/Auth/RegisterStepInvites.vue';

    export interface RegisterFormData {
        name: string;
        email: string;
        password: string;
        password_confirmation: string;
        terms_accepted: boolean;
        company: {
            name: string;
            ico: string;
            dic: string | null;
            vat_number: string | null;
            is_vat_payer: boolean;
            address_line: string;
            city: string;
            postal_code: string;
            country: string;
        };
        invites: { email: string; role_name: string }[];
    }

    defineProps<{
        roles: string[];
    }>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const lookup = useIcoLookup();

    const step = reactive({ index: 1 });

    const languages = computed(() => pageProps.languages ?? []);
    const currentLocale = computed(() => pageProps.locale ?? 'sk');

    const form = useForm<RegisterFormData>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms_accepted: false,
        company: {
            name: '',
            ico: '',
            dic: null,
            vat_number: null,
            is_vat_payer: false,
            address_line: '',
            city: '',
            postal_code: '',
            country: 'SK',
        },
        invites: [
            { email: '', role_name: '' },
            { email: '', role_name: '' },
            { email: '', role_name: '' },
        ],
    });

    const stepTitle = computed<string>(() => {
        if (step.index === 1) {
            return t('register.step1_title');
        }
        if (step.index === 2) {
            return t('register.step2_title');
        }
        return t('register.step3_title');
    });

    function next() {
        if (step.index < 3) {
            step.index++;
        }
    }

    function back() {
        if (step.index > 1) {
            step.index--;
        }
    }

    function submit() {
        form
            .transform((data) => ({
                ...data,
                invites: data.invites.filter((inv) => inv.email.trim() !== ''),
            }))
            .post('/register', {
                onSuccess: () => {
                    router.visit('/dashboard');
                },
            });
    }

    function switchLocale(code: string) {
        router.get(`/language/${code}`, {}, { preserveState: false });
    }
</script>

<template>
    <Head :title="t('register.title')" />

    <div class="min-h-screen flex relative">
        <!-- Logo top-left (mobile only — hero panel is hidden on mobile) -->
        <Link
            href="/"
            class="absolute top-4 left-6 z-20 flex items-center gap-2 font-bold tracking-tight text-white transition hover:opacity-90 lg:hidden"
        >
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
        </Link>

        <!-- Hero (left 40%) -->
        <RegisterHero :current-step="step.index" />

        <!-- Form (right 60%) -->
        <div class="relative flex flex-1 flex-col items-center justify-center bg-white px-8 py-12 lg:px-16">
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
                <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white auth-mobile-logo-bg">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M5 14c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke-linecap="round" />
                        <path d="M8 17h8" stroke-linecap="round" />
                    </svg>
                </span>
                <span class="text-lg font-bold text-slate-900">{{ t('app_name') }}</span>
            </Link>

            <div class="w-full max-w-[420px]">
                <div class="mb-7">
                    <h2 class="text-[26px] font-bold text-slate-900">{{ t('register.title') }}</h2>
                    <p class="mt-1.5 text-sm text-slate-500">{{ stepTitle }}</p>
                </div>

                <!-- Mobile step indicator -->
                <div class="mb-5 flex items-center gap-2 lg:hidden">
                    <div
                        v-for="s in [1, 2, 3]"
                        :key="s"
                        class="h-1.5 flex-1 rounded-full transition-all"
                        :class="s <= step.index ? 'bg-amber-600' : 'bg-slate-200'"
                    />
                </div>

                <form novalidate @submit.prevent>
                    <RegisterStepAccount v-if="step.index === 1" :form="form" @next="next" />
                    <RegisterStepCompany
                        v-else-if="step.index === 2"
                        :form="form"
                        :lookup="lookup"
                        @next="next"
                        @back="back"
                    />
                    <RegisterStepInvites
                        v-else-if="step.index === 3"
                        :form="form"
                        :roles="roles"
                        @back="back"
                        @submit="submit"
                    />
                </form>

                <!-- Already have account -->
                <div class="mt-6 text-center text-[13px] text-slate-500">
                    {{ t('register.already_have_account') }}
                    <Link href="/login" class="font-medium auth-link ml-1">{{ t('register.sign_in') }}</Link>
                </div>

                <!-- Lang switcher -->
                <div class="mt-4 flex items-center justify-center gap-3 text-[12px] text-slate-500">
                    <template v-for="(lang, i) in languages" :key="lang.code">
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
            </div>
        </div>
    </div>
</template>
