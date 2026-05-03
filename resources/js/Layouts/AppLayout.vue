<script setup lang="ts">
    import { computed } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useTranslate } from '@/Composables/useTranslate';

    interface NavItem {
        key: string;
        label: string;
        href?: string;
        can?: string;
    }

    const props = usePageProps();
    const { t } = useTranslate();

    const user = computed(() => props.auth?.user);
    const tenant = computed(() => props.tenant?.active);
    const tenants = computed(() => props.tenant?.available ?? []);
    const can = computed(() => props.can ?? {});
    const languages = computed(() => props.languages ?? []);
    const currentLocale = computed(() => props.locale ?? 'sk');

    const navItems: NavItem[] = [
        { key: 'dashboard', label: 'dashboard', href: '/dashboard' },
        { key: 'clients', label: 'nav.clients', can: 'viewClients' },
        { key: 'objects', label: 'nav.objects', can: 'viewObjects' },
        { key: 'quotes', label: 'nav.quotes', can: 'viewQuotes' },
        { key: 'contracts', label: 'nav.contracts', can: 'viewContracts' },
        { key: 'employees', label: 'nav.employees', can: 'viewEmployees' },
        { key: 'schedule', label: 'nav.schedule', can: 'viewSchedule' },
        { key: 'invoices', label: 'nav.invoices', can: 'viewInvoices' },
        { key: 'templates', label: 'nav.templates', can: 'viewTemplates' },
    ];

    const visibleNav = computed(() => navItems.filter((item) => !item.can || can.value[item.can]));

    function logout() {
        router.post('/logout');
    }

    function switchLocale(code: string) {
        router.get(`/language/${code}`, {}, { preserveState: false });
    }
</script>

<template>
    <div class="drawer lg:drawer-open">
        <input id="app-drawer" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content flex flex-col min-h-screen bg-base-200">
            <header class="navbar bg-base-100 border-b border-base-300 lg:hidden">
                <div class="flex-1 px-4">
                    <label for="app-drawer" class="btn btn-square btn-ghost btn-sm">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </label>
                    <span class="ml-3 font-semibold">{{ tenant?.name ?? t('app_name') }}</span>
                </div>
            </header>

            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>

        <aside class="drawer-side z-20">
            <label for="app-drawer" aria-label="close sidebar" class="drawer-overlay" />

            <div class="bg-base-100 min-h-full w-72 p-4 flex flex-col gap-4 border-r border-base-300">
                <div class="px-2 py-3">
                    <Link href="/dashboard" class="text-xl font-bold text-primary">{{ t('app_name') }}</Link>
                    <p v-if="tenant" class="text-sm text-base-content/70 mt-1">{{ tenant.name }}</p>
                </div>

                <ul class="menu menu-md w-full px-0 grow">
                    <li v-for="item in visibleNav" :key="item.key">
                        <Link v-if="item.href" :href="item.href">{{ t(item.label) }}</Link>
                        <span v-else class="opacity-60 cursor-not-allowed">{{ t(item.label) }}</span>
                    </li>
                </ul>

                <div v-if="user" class="border-t border-base-300 pt-3">
                    <div class="flex items-center gap-3 px-2 py-2">
                        <div class="avatar avatar-placeholder">
                            <div class="bg-primary text-primary-content w-10 rounded-full">
                                <span>{{ user.name.charAt(0).toUpperCase() }}</span>
                            </div>
                        </div>
                        <div class="grow">
                            <p class="font-semibold text-sm">{{ user.name }}</p>
                            <p class="text-xs text-base-content/60">{{ user.email }}</p>
                        </div>
                    </div>

                    <div class="flex gap-2 mt-2">
                        <div class="dropdown dropdown-top">
                            <div tabindex="0" role="button" class="btn btn-sm btn-ghost">
                                {{ languages.find((l) => l.code === currentLocale)?.flag }}
                                <span class="ml-1">{{ currentLocale.toUpperCase() }}</span>
                            </div>
                            <ul
                                tabindex="0"
                                class="dropdown-content menu bg-base-100 rounded-box z-10 mb-2 w-44 p-2 shadow"
                            >
                                <li v-for="lang in languages" :key="lang.code">
                                    <button
                                        type="button"
                                        :class="{ active: lang.code === currentLocale }"
                                        @click="switchLocale(lang.code)"
                                    >
                                        <span>{{ lang.flag }}</span>
                                        <span>{{ lang.label }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <button type="button" class="btn btn-sm btn-ghost ml-auto" @click="logout">
                            {{ t('logout') }}
                        </button>
                    </div>

                    <div v-if="tenants.length > 1" class="mt-3 px-2">
                        <p class="text-xs text-base-content/60 uppercase tracking-wide mb-1">
                            {{ tenants.length }} {{ t('nav.tenants') }}
                        </p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</template>
