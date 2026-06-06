<script setup lang="ts">
    import { computed } from 'vue';
    import { Link, router, usePage } from '@inertiajs/vue3';
    import {
        Squares2X2Icon,
        UsersIcon,
        BuildingOfficeIcon,
        DocumentTextIcon,
        ClipboardDocumentListIcon,
        CalendarDaysIcon,
        UserGroupIcon,
        ReceiptPercentIcon,
        FolderIcon,
        BellIcon,
        ShieldCheckIcon,
        Cog6ToothIcon,
        ChevronDownIcon,
        ChevronUpDownIcon,
    } from '@heroicons/vue/24/outline';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useTranslate } from '@/Composables/useTranslate';
    import { useCapabilitiesStore } from '@/stores/capabilities';

    const props = usePageProps();
    const { t } = useTranslate();
    const page = usePage();
    const capabilitiesStore = useCapabilitiesStore();

    const user = computed(() => props.auth?.user);
    const tenant = computed(() => props.tenant?.active);
    const can = computed(() => props.can ?? {});
    const languages = computed(() => props.languages ?? []);
    const currentLocale = computed(() => props.locale ?? 'sk');
    const pageComponent = computed(() => `${page.component}::${(page.props.locale as string) ?? 'sk'}`);

    interface NavItem {
        key: string;
        label: string;
        href: string;
        icon: unknown;
        can?: string;
    }

    const navItems: NavItem[] = [
        { key: 'dashboard', label: 'dashboard', href: '/dashboard', icon: Squares2X2Icon },
        { key: 'clients', label: 'nav.clients', href: '/clients', icon: UsersIcon, can: 'viewClients' },
        {
            key: 'objects',
            label: 'nav.objects',
            href: '/objects',
            icon: BuildingOfficeIcon,
            can: 'viewObjects',
        },
        { key: 'quotes', label: 'nav.quotes', href: '/quotes', icon: DocumentTextIcon, can: 'viewQuotes' },
        {
            key: 'contracts',
            label: 'nav.contracts',
            href: '/contracts',
            icon: ClipboardDocumentListIcon,
            can: 'viewContracts',
        },
        {
            key: 'schedule',
            label: 'nav.schedule',
            href: '/schedule',
            icon: CalendarDaysIcon,
            can: 'viewSchedule',
        },
        {
            key: 'employees',
            label: 'nav.employees',
            href: '/employees',
            icon: UserGroupIcon,
            can: 'viewEmployees',
        },
        {
            key: 'invoices',
            label: 'nav.invoices',
            href: '/invoices',
            icon: ReceiptPercentIcon,
            can: 'viewInvoices',
        },
        {
            key: 'templates',
            label: 'nav.templates',
            href: '/templates',
            icon: FolderIcon,
            can: 'viewTemplates',
        },
    ];

    const adminNavItems: NavItem[] = [
        { key: 'permissions', label: 'nav.permissions', href: '/permissions', icon: ShieldCheckIcon },
        { key: 'settings', label: 'nav.settings', href: '/settings', icon: Cog6ToothIcon },
    ];

    const visibleNav = computed(() =>
        navItems.filter((item) => !item.can || can.value[item.can as keyof typeof can.value]),
    );

    function isActive(href: string): boolean {
        return page.url.startsWith(href);
    }

    function logout() {
        capabilitiesStore.reset();
        router.post('/logout');
    }

    function switchLocale(code: string) {
        router.get(`/language/${code}`, {}, { preserveState: false });
    }
</script>

<template>
    <div class="app-shell">
        <!-- Topbar -->
        <header class="app-topbar">
            <div
                class="flex items-center gap-2 px-2 py-1.5 rounded-md bg-slate-100 border border-slate-200 text-sm font-medium text-slate-700 cursor-pointer hover:bg-slate-200 transition select-none"
            >
                <span class="h-2 w-2 rounded-full bg-amber-600 flex-shrink-0" />
                <span class="max-w-[200px] truncate">{{ tenant?.name ?? t('app_name') }}</span>
                <ChevronUpDownIcon class="h-3.5 w-3.5 text-slate-400 flex-shrink-0" />
            </div>

            <div class="flex-1" />

            <!-- Lang switcher -->
            <div class="flex items-center gap-0.5 rounded-md bg-slate-100 p-[3px]">
                <button
                    v-for="lang in languages"
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

            <!-- Bell -->
            <button
                type="button"
                class="relative flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition"
            >
                <BellIcon class="h-5 w-5" />
                <span
                    class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500 border-2 border-white"
                />
            </button>

            <!-- User -->
            <button
                type="button"
                class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2.5 hover:bg-slate-100 transition"
                @click="logout"
            >
                <span
                    class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-purple-600 text-xs font-bold text-white flex-shrink-0"
                >
                    {{ user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}
                </span>
                <span class="text-[13px] font-semibold text-slate-800">{{ user?.name }}</span>
                <ChevronDownIcon class="h-3 w-3 text-slate-400" />
            </button>
        </header>

        <!-- Sidebar -->
        <aside class="app-sidebar">
            <!-- Logo -->
            <div class="px-3 pb-5 pt-1">
                <Link
                    href="/dashboard"
                    class="flex items-center gap-2 text-white hover:opacity-90 transition"
                >
                    <span
                        class="flex h-7 w-7 items-center justify-center rounded-[7px] bg-gradient-to-br from-[#A16207] to-[#713F12] flex-shrink-0"
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
                    <span class="text-[16px] font-bold tracking-tight">{{ t('app_name') }}</span>
                </Link>
            </div>

            <!-- Main nav -->
            <nav class="flex flex-col gap-0.5 px-2">
                <Link
                    v-for="item in visibleNav"
                    :key="item.key"
                    :href="item.href"
                    class="nav-item"
                    :class="isActive(item.href) ? 'nav-item-active' : 'nav-item-idle'"
                >
                    <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                    <span>{{ t(item.label) }}</span>
                </Link>
            </nav>

            <!-- Admin section -->
            <div class="mt-4 border-t border-white/[0.06] pt-3 px-2">
                <div class="px-2 pb-1.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500">
                    Administrácia
                </div>
                <nav class="flex flex-col gap-0.5">
                    <Link
                        v-for="item in adminNavItems"
                        :key="item.key"
                        :href="item.href"
                        class="nav-item"
                        :class="isActive(item.href) ? 'nav-item-active' : 'nav-item-idle'"
                    >
                        <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                        <span>{{ t(item.label) }}</span>
                    </Link>
                </nav>
            </div>

            <div class="flex-1" />
        </aside>

        <!-- Content with content-only transition -->
        <main class="app-content">
            <Transition name="content" mode="out-in">
                <div :key="pageComponent">
                    <slot />
                </div>
            </Transition>
        </main>
    </div>
</template>

<style scoped>
    .app-shell {
        display: grid;
        grid-template-columns: 260px 1fr;
        grid-template-rows: 56px 1fr;
        height: 100vh;
        overflow: hidden;
    }

    .app-topbar {
        grid-column: 1 / 3;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0 20px;
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        z-index: 10;
    }

    .app-sidebar {
        background: #0f172a;
        display: flex;
        flex-direction: column;
        padding: 16px 0;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .app-content {
        background: #f8fafc;
        overflow-y: auto;
        padding: 28px;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 7px 10px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.12s;
        text-decoration: none;
    }

    .nav-item-idle {
        color: #94a3b8;
    }

    .nav-item-idle:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #e2e8f0;
    }

    .nav-item-active {
        background: #a16207;
        color: #ffffff;
        box-shadow: 0 1px 3px rgba(161, 98, 7, 0.35);
    }

    .nav-item-active:hover {
        background: #713f12;
    }
</style>
