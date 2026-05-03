<script setup lang="ts">
    import { computed } from 'vue';
    import { Head, Link, router } from '@inertiajs/vue3';
    import {
        SparklesIcon,
        CheckIcon,
        DocumentTextIcon,
        UserGroupIcon,
        ReceiptPercentIcon,
        BuildingOffice2Icon,
        ShieldCheckIcon,
        DevicePhoneMobileIcon,
        ArrowRightIcon,
    } from '@heroicons/vue/24/outline';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';

    const { t } = useTranslate();
    const props = usePageProps();
    const currentLocale = computed(() => props.locale ?? 'sk');

    function switchLocale(code: string) {
        router.get(`/language/${code}`, {}, { preserveState: false });
    }

    const features = [
        { icon: DocumentTextIcon, key: 'docs' },
        { icon: UserGroupIcon, key: 'staff' },
        { icon: ReceiptPercentIcon, key: 'invoicing' },
        { icon: BuildingOffice2Icon, key: 'clients' },
        { icon: ShieldCheckIcon, key: 'permissions' },
        { icon: DevicePhoneMobileIcon, key: 'mobile' },
    ] as const;

    interface Plan {
        name: string;
        price: string;
        firms: string;
        users: string;
        clients: string;
        features: string[];
        popular?: boolean;
    }

    const plans: Plan[] = [
        {
            name: 'Free',
            price: '0',
            firms: '1',
            users: '1',
            clients: '5',
            features: ['Základné funkcie', 'SK podpora'],
        },
        {
            name: 'Štart',
            price: '19',
            firms: '1',
            users: '3',
            clients: '∞',
            features: ['Všetko z Free', 'Šablóny dokumentov', 'PDF export'],
            popular: true,
        },
        {
            name: 'Business',
            price: '39',
            firms: '3',
            users: '10',
            clients: '∞',
            features: ['Multi-firma (3)', 'Mobilná app', 'API prístup'],
        },
        {
            name: 'Premium',
            price: '69',
            firms: '∞',
            users: '∞',
            clients: '∞',
            features: ['Všetko bez limitov', 'Prioritná podpora', 'Vlastný branding'],
        },
    ];

    const navMockItems = [
        { label: 'Dashboard', icon: '▦', active: true },
        { label: 'Klienti', icon: '◫' },
        { label: 'Cenové ponuky', icon: '◰' },
        { label: 'Zmluvy', icon: '◧' },
        { label: 'Rozvrh', icon: '◑' },
        { label: 'Faktúry', icon: '◨' },
        { label: 'Zamestnanci', icon: '◐' },
    ];

    const stats = [
        { label: 'Dnes', value: '8', tone: 'text-blue-600' },
        { label: 'Bez upratovačky', value: '2', tone: 'text-red-600' },
        { label: 'Nefakturované', value: '14', tone: 'text-amber-600' },
        { label: 'Končiace', value: '3', tone: 'text-amber-600' },
    ];

    const todayJobs = [
        '08:00 · Hlavná 5 · Anna N.',
        '10:30 · Štúrova 12 · Mária K.',
        '14:00 · Mickiewiczova 3 · Jana S.',
    ];
</script>

<template>
    <Head :title="t('app_name')" />

    <div class="min-h-screen bg-white font-sans text-slate-800">
        <!-- Top nav -->
        <nav
            class="sticky top-0 z-10 flex items-center gap-7 border-b border-slate-100 bg-white/85 px-6 py-4 backdrop-blur-md md:px-12"
        >
            <Link href="/" class="mr-auto flex items-center gap-2 font-bold tracking-tight text-slate-900">
                <span
                    class="flex h-7 w-7 items-center justify-center rounded-md bg-gradient-to-br from-blue-600 to-blue-700 text-white shadow"
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

            <a
                class="hidden text-sm font-medium text-slate-700 hover:text-slate-900 md:inline"
                href="#features"
            >
                {{ t('landing.nav.features') }}
            </a>
            <a
                class="hidden text-sm font-medium text-slate-700 hover:text-slate-900 md:inline"
                href="#pricing"
            >
                {{ t('landing.nav.pricing') }}
            </a>
            <a class="hidden text-sm font-medium text-slate-700 hover:text-slate-900 lg:inline" href="#about">
                {{ t('landing.nav.about') }}
            </a>
            <a
                class="hidden text-sm font-medium text-slate-700 hover:text-slate-900 lg:inline"
                href="#contact"
            >
                {{ t('landing.nav.contact') }}
            </a>

            <div class="hidden items-center gap-0.5 rounded-md bg-slate-100 p-[3px] sm:flex">
                <button
                    v-for="lang in props.languages ?? []"
                    :key="lang.code"
                    type="button"
                    class="rounded px-2 py-[3px] text-[11px] font-semibold uppercase transition"
                    :class="
                        lang.code === currentLocale
                            ? 'bg-white text-slate-900 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700'
                    "
                    @click="switchLocale(lang.code)"
                >
                    {{ lang.code }}
                </button>
            </div>

            <Link
                href="/login"
                class="hidden h-9 items-center rounded-md px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900 sm:inline-flex"
            >
                {{ t('login') }}
            </Link>
            <Link
                href="/login"
                class="inline-flex h-9 items-center rounded-md bg-blue-600 px-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 hover:shadow"
            >
                {{ t('landing.nav.try_free') }}
            </Link>
        </nav>

        <!-- Hero -->
        <section class="mx-auto max-w-[1100px] px-6 pt-20 pb-16 text-center md:px-12">
            <span
                class="mb-7 inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-[5px] text-xs font-semibold text-blue-700"
            >
                <SparklesIcon class="h-3 w-3" />
                {{ t('landing.hero.badge') }}
            </span>

            <h1 class="mb-5 text-5xl leading-[1.05] font-bold tracking-tight text-slate-900 md:text-6xl">
                {{ t('landing.hero.title_1') }}
                <br />
                <span class="bg-gradient-to-br from-blue-600 to-indigo-500 bg-clip-text text-transparent">
                    {{ t('landing.hero.title_2') }}
                </span>
            </h1>
            <p class="mx-auto mb-8 max-w-[640px] text-lg leading-relaxed text-slate-600 md:text-xl">
                {{ t('landing.hero.subtitle') }}
            </p>

            <div class="flex flex-wrap justify-center gap-3">
                <Link
                    href="/login"
                    class="inline-flex h-11 items-center gap-2 rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow transition hover:bg-blue-700 hover:shadow-lg"
                >
                    {{ t('landing.hero.cta_primary') }}
                    <ArrowRightIcon class="h-4 w-4" />
                </Link>
                <button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-md border border-slate-200 bg-white px-5 text-sm font-semibold text-blue-600 transition hover:border-blue-600 hover:bg-slate-50"
                >
                    {{ t('landing.hero.cta_secondary') }}
                </button>
            </div>
            <div class="mt-5 text-[13px] text-slate-500">{{ t('landing.hero.fineprint') }}</div>

            <!-- Mock dashboard -->
            <div
                class="mx-auto mt-14 max-w-[1100px] overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-2xl"
            >
                <div class="flex h-7 items-center gap-1.5 border-b border-slate-200 bg-slate-100 px-3">
                    <span class="h-2.5 w-2.5 rounded-full bg-[#FF5F57]" />
                    <span class="h-2.5 w-2.5 rounded-full bg-[#FEBC2E]" />
                    <span class="h-2.5 w-2.5 rounded-full bg-[#28C840]" />
                    <div
                        class="ml-3 flex h-[18px] flex-1 items-center justify-center rounded-md bg-white text-[11px] text-slate-500"
                    >
                        app.cleanmaster.sk/dashboard
                    </div>
                </div>
                <div class="grid h-[380px] grid-cols-[180px_1fr] bg-slate-50">
                    <aside class="flex flex-col gap-1 bg-slate-900 p-3">
                        <div
                            v-for="(item, i) in navMockItems"
                            :key="item.label"
                            class="flex items-center gap-2 rounded-[5px] px-2 py-1.5 text-[11px]"
                            :class="item.active ? 'bg-blue-600 text-white' : 'text-slate-400'"
                        >
                            <span class="text-xs">{{ item.icon }}</span>
                            {{ item.label }}
                            <span v-if="i === 0" class="sr-only">active</span>
                        </div>
                    </aside>
                    <div class="p-5">
                        <div class="mb-4 text-lg font-bold text-slate-900">Dashboard</div>
                        <div class="mb-4 grid grid-cols-4 gap-2.5">
                            <div
                                v-for="s in stats"
                                :key="s.label"
                                class="rounded-md border border-slate-200 bg-white p-2.5"
                            >
                                <div class="text-[9px] font-semibold tracking-wide text-slate-500 uppercase">
                                    {{ s.label }}
                                </div>
                                <div class="text-2xl font-bold" :class="s.tone">{{ s.value }}</div>
                            </div>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-white p-3 text-left">
                            <div class="mb-2.5 text-xs font-bold">Dnešné zákazky</div>
                            <div
                                v-for="(row, i) in todayJobs"
                                :key="row"
                                class="flex items-center justify-between py-1.5 text-[11px] text-slate-700"
                                :class="i ? 'border-t border-slate-100' : ''"
                            >
                                <span>{{ row }}</span>
                                <span
                                    class="rounded-lg bg-emerald-100 px-1.5 py-px text-[9px] font-semibold text-emerald-700"
                                >
                                    Plánovaná
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="mx-auto max-w-[1200px] px-6 py-20 md:px-12">
            <h2 class="mb-12 text-center text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">
                {{ t('landing.features.title') }}
            </h2>
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="f in features"
                    :key="f.key"
                    class="rounded-2xl border border-slate-200 bg-slate-50 p-8 transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div
                        class="mb-4 flex h-11 w-11 items-center justify-center rounded-[10px] bg-blue-100 text-blue-600"
                    >
                        <component :is="f.icon" class="h-5 w-5" />
                    </div>
                    <h3 class="mb-2 text-lg font-bold text-slate-900">
                        {{ t(`landing.features.${f.key}.title`) }}
                    </h3>
                    <p class="text-sm leading-relaxed text-slate-600">
                        {{ t(`landing.features.${f.key}.desc`) }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Pricing -->
        <section id="pricing" class="bg-slate-50">
            <div class="mx-auto max-w-[1200px] px-6 py-20 md:px-12">
                <div class="text-center">
                    <h2 class="mb-2 text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">
                        {{ t('landing.pricing.title') }}
                    </h2>
                    <p class="text-base text-slate-600 md:text-lg">{{ t('landing.pricing.subtitle') }}</p>
                </div>
                <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="plan in plans"
                        :key="plan.name"
                        class="relative rounded-2xl border border-slate-200 bg-white px-6 py-8"
                        :class="
                            plan.popular ? 'border-blue-600 ring-[3px] ring-blue-100 lg:-translate-y-2' : ''
                        "
                    >
                        <span
                            v-if="plan.popular"
                            class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-xl bg-blue-600 px-3.5 py-1 text-[11px] font-bold tracking-wider text-white uppercase"
                        >
                            {{ t('landing.pricing.popular') }}
                        </span>
                        <h4 class="text-lg font-bold text-slate-900">{{ plan.name }}</h4>
                        <div class="mt-2 mb-1 text-4xl font-bold tracking-tight text-slate-900">
                            {{ plan.price }} €
                            <small class="text-sm font-medium text-slate-500">{{
                                t('landing.pricing.month')
                            }}</small>
                        </div>
                        <ul class="my-5 flex flex-col gap-2">
                            <li class="flex items-center gap-2 text-sm text-slate-700">
                                <CheckIcon class="h-3.5 w-3.5 text-emerald-600" />
                                {{ t('landing.pricing.firms') }}: {{ plan.firms }}
                            </li>
                            <li class="flex items-center gap-2 text-sm text-slate-700">
                                <CheckIcon class="h-3.5 w-3.5 text-emerald-600" />
                                {{ t('landing.pricing.users') }}: {{ plan.users }}
                            </li>
                            <li class="flex items-center gap-2 text-sm text-slate-700">
                                <CheckIcon class="h-3.5 w-3.5 text-emerald-600" />
                                {{ t('landing.pricing.clients') }}: {{ plan.clients }}
                            </li>
                            <li
                                v-for="feat in plan.features"
                                :key="feat"
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <CheckIcon class="h-3.5 w-3.5 text-emerald-600" />
                                {{ feat }}
                            </li>
                        </ul>
                        <Link
                            href="/login"
                            class="inline-flex h-9 w-full items-center justify-center rounded-md text-sm font-semibold transition"
                            :class="
                                plan.popular
                                    ? 'bg-blue-600 text-white shadow-sm hover:bg-blue-700'
                                    : 'border border-slate-200 bg-white text-blue-600 hover:border-blue-600 hover:bg-slate-50'
                            "
                        >
                            {{ t('landing.pricing.choose') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section
            class="bg-gradient-to-br from-blue-600 to-blue-800 px-6 py-20 text-center text-white md:px-12"
        >
            <h2 class="text-3xl font-bold tracking-tight md:text-4xl">{{ t('landing.cta.title') }}</h2>
            <p class="mt-3 text-base text-white/85 md:text-lg">{{ t('landing.cta.subtitle') }}</p>
            <form
                class="mx-auto mt-7 inline-flex max-w-full gap-2 rounded-xl bg-white/10 p-1.5 backdrop-blur"
                @submit.prevent
            >
                <input
                    type="email"
                    :placeholder="t('landing.cta.placeholder')"
                    class="h-10 min-w-0 rounded-md bg-transparent px-3 text-sm text-white placeholder-white/60 focus:outline-none sm:min-w-[280px]"
                />
                <button
                    type="submit"
                    class="inline-flex h-10 items-center gap-2 rounded-md bg-white px-4 text-sm font-semibold text-blue-600 transition hover:bg-blue-50"
                >
                    {{ t('landing.cta.start') }}
                    <ArrowRightIcon class="h-4 w-4" />
                </button>
            </form>
        </section>

        <!-- Footer -->
        <footer
            class="flex flex-wrap items-center justify-between gap-3 bg-slate-900 px-6 py-8 text-sm text-slate-400 md:px-12"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex h-6 w-6 items-center justify-center rounded bg-gradient-to-br from-blue-600 to-blue-700 text-white"
                >
                    <svg
                        viewBox="0 0 24 24"
                        class="h-3.5 w-3.5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                    >
                        <path d="M5 14c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke-linecap="round" />
                        <path d="M8 17h8" stroke-linecap="round" />
                    </svg>
                </span>
                <span>{{ t('landing.footer.copy') }}</span>
            </div>
            <div class="flex items-center gap-6">
                <a class="hover:text-white" href="#contact">{{ t('landing.footer.contact') }}</a>
                <a class="hover:text-white" href="#terms">{{ t('landing.footer.terms') }}</a>
                <a class="hover:text-white" href="#gdpr">{{ t('landing.footer.gdpr') }}</a>
            </div>
        </footer>
    </div>
</template>
