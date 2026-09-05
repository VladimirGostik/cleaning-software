<script setup lang="ts">
    import { computed } from 'vue';

    import { ref } from 'vue';
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
        PlusIcon,
    } from '@heroicons/vue/24/outline';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useTranslate } from '@/Composables/useTranslate';
    import { useCapabilitiesStore } from '@/stores/capabilities';
    import { useNotificationsStore } from '@/stores/notifications';
    import { useAuthorization } from '@/Composables/useAuthorization';
    import AddTenantModal from '@/Pages/Tenants/AddTenantModal.vue';
    import ConfirmDialog from '@/Components/ConfirmDialog.vue';
    import NotificationBell from '@/Components/Notifications/NotificationBell.vue';

    const props = usePageProps();
    const { t } = useTranslate();
    const page = usePage(); // kept for page.component (transition key) and page.url only
    const capabilitiesStore = useCapabilitiesStore();
    const notificationsStore = useNotificationsStore();
    const { can } = useAuthorization();

    const user = computed(() => props.auth?.user);
    const tenant = computed(() => props.tenant?.active);
    const languages = computed(() => props.languages ?? []);
    const currentLocale = computed(() => props.locale ?? 'sk');
    const tenantColors = computed(() => props.tenantColors ?? []);
    const pageComponent = computed(() => `${page.component}::${props.locale ?? 'sk'}`);

    // eslint-disable-next-line no-restricted-syntax -- imperative UI toggle: tenant dropdown
    const isTenantMenuOpen = ref(false);
    // eslint-disable-next-line no-restricted-syntax -- imperative UI toggle: add tenant modal
    const isAddTenantOpen = ref(false);
    // eslint-disable-next-line no-restricted-syntax -- imperative UI toggle: user menu dropdown
    const isUserMenuOpen = ref(false);
    // eslint-disable-next-line no-restricted-syntax -- imperative UI toggle: logout confirm modal
    const isLogoutConfirmOpen = ref(false);

    interface NavItem {
        key: string;
        label: string;
        href: string;
        icon: unknown;
        can?: App.Enums.PermissionEnum;
        implemented: boolean;
    }

    const navItems: NavItem[] = [
        { key: 'dashboard', label: 'dashboard', href: '/dashboard', icon: Squares2X2Icon, implemented: true },
        {
            key: 'clients',
            label: 'nav.clients',
            href: '/clients',
            icon: UsersIcon,
            can: 'view clients',
            implemented: true,
        },
        {
            key: 'objects',
            label: 'nav.objects',
            href: '/objects',
            icon: BuildingOfficeIcon,
            can: 'view objects',
            implemented: true,
        },
        {
            key: 'quotes',
            label: 'nav.quotes',
            href: '/quotes',
            icon: DocumentTextIcon,
            can: 'view quotes',
            implemented: true,
        },
        {
            key: 'contracts',
            label: 'nav.contracts',
            href: '/contracts',
            icon: ClipboardDocumentListIcon,
            can: 'view contracts',
            implemented: true,
        },
        {
            key: 'contract_templates',
            label: 'nav.contract_templates',
            href: '/contract-templates',
            icon: FolderIcon,
            can: 'view contract_templates',
            implemented: true,
        },
        {
            key: 'schedule',
            label: 'nav.schedule',
            href: '/jobs',
            icon: CalendarDaysIcon,
            can: 'view schedule',
            implemented: true,
        },
        {
            key: 'employees',
            label: 'nav.employees',
            href: '/employees',
            icon: UserGroupIcon,
            can: 'view employees',
            implemented: true,
        },
        {
            key: 'invoices',
            label: 'nav.invoices',
            href: '/invoices',
            icon: ReceiptPercentIcon,
            can: 'view invoices',
            implemented: true,
        },
        {
            key: 'notifications',
            label: 'nav.notifications',
            href: '/notifications',
            icon: BellIcon,
            can: 'view notifications',
            implemented: true,
        },
        {
            key: 'templates',
            label: 'nav.templates',
            href: '/templates',
            icon: FolderIcon,
            can: 'view templates',
            implemented: false,
        },
    ];

    const adminNavItems: NavItem[] = [
        {
            key: 'permissions',
            label: 'nav.permissions',
            href: '/permissions',
            icon: ShieldCheckIcon,
            implemented: false,
        },
        {
            key: 'settings',
            label: 'nav.settings',
            href: '/settings',
            icon: Cog6ToothIcon,
            implemented: false,
        },
    ];

    const visibleNav = computed(() => navItems.filter((item) => !item.can || can(item.can)));

    function isActive(href: string): boolean {
        return page.url.startsWith(href);
    }

    function logout() {
        capabilitiesStore.reset();
        notificationsStore.reset();
        router.post('/logout');
    }

    function switchLocale(code: string) {
        router.get(`/language/${code}`, {}, { preserveScroll: true });
    }

    function openAddTenant() {
        isTenantMenuOpen.value = false;
        isAddTenantOpen.value = true;
    }
</script>

<template>
    <div class="app-shell">
        <!-- Topbar -->
        <header class="app-topbar">
            <!-- Tenant chip with dropdown -->
            <div class="relative">
                <button
                    type="button"
                    class="flex items-center gap-2 px-2 py-1.5 rounded-md bg-base-200 border border-base-300 text-sm font-medium text-base-content cursor-pointer hover:bg-base-300 transition select-none"
                    :aria-expanded="isTenantMenuOpen"
                    aria-haspopup="true"
                    @click="isTenantMenuOpen = !isTenantMenuOpen"
                >
                    <span class="h-2 w-2 rounded-full bg-primary flex-shrink-0" />
                    <span class="max-w-[200px] truncate">{{ tenant?.name ?? t('app_name') }}</span>
                    <ChevronUpDownIcon class="h-3.5 w-3.5 text-base-content/40 flex-shrink-0" />
                </button>

                <!-- Dropdown -->
                <Transition name="dropdown">
                    <div
                        v-if="isTenantMenuOpen"
                        class="absolute left-0 top-full mt-1 z-30 min-w-[220px] rounded-lg border border-base-300 bg-base-100 shadow-lg py-1"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-base-content hover:bg-base-200 transition"
                            @click="openAddTenant"
                        >
                            <PlusIcon class="h-4 w-4 text-base-content/40" />
                            {{ t('nav.add_tenant') }}
                        </button>
                    </div>
                </Transition>

                <!-- Click-outside overlay -->
                <div v-if="isTenantMenuOpen" class="fixed inset-0 z-20" @click="isTenantMenuOpen = false" />
            </div>

            <div class="flex-1" />

            <!-- Lang switcher -->
            <div class="flex items-center gap-0.5 rounded-md bg-base-200 p-[3px]">
                <button
                    v-for="lang in languages"
                    :key="lang.code"
                    type="button"
                    class="rounded px-2 py-[3px] text-[11px] font-semibold uppercase transition"
                    :class="
                        lang.code === currentLocale
                            ? 'bg-base-100 text-base-content shadow-sm'
                            : 'text-base-content/60 hover:text-base-content'
                    "
                    @click="switchLocale(lang.code)"
                >
                    {{ lang.code }}
                </button>
            </div>

            <!-- Bell -->
            <NotificationBell />

            <!-- User -->
            <div class="relative">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2.5 hover:bg-base-200 transition"
                    :aria-expanded="isUserMenuOpen"
                    aria-haspopup="true"
                    @click="isUserMenuOpen = !isUserMenuOpen"
                >
                    <span
                        class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-primary/80 to-primary text-xs font-bold text-primary-content flex-shrink-0"
                    >
                        {{ user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}
                    </span>
                    <span class="text-[13px] font-semibold text-base-content">{{ user?.name }}</span>
                    <ChevronDownIcon class="h-3 w-3 text-base-content/40" />
                </button>

                <!-- User dropdown -->
                <Transition name="dropdown">
                    <div
                        v-if="isUserMenuOpen"
                        class="absolute right-0 top-full mt-1 z-30 min-w-[180px] rounded-lg border border-base-300 bg-base-100 shadow-lg py-1"
                    >
                        <div
                            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-base-content/40 cursor-not-allowed opacity-50"
                            aria-disabled="true"
                        >
                            {{ t('nav.profile') }}
                        </div>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2 text-sm text-base-content hover:bg-base-200 transition"
                            @click="
                                isUserMenuOpen = false;
                                isLogoutConfirmOpen = true;
                            "
                        >
                            {{ t('logout') }}
                        </button>
                    </div>
                </Transition>

                <!-- Click-outside overlay -->
                <div v-if="isUserMenuOpen" class="fixed inset-0 z-20" @click="isUserMenuOpen = false" />
            </div>
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
                        class="flex h-7 w-7 items-center justify-center rounded-[7px] bg-gradient-to-br from-primary/80 to-primary flex-shrink-0"
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
                <template v-for="item in visibleNav" :key="item.key">
                    <Link
                        v-if="item.implemented"
                        :href="item.href"
                        class="nav-item"
                        :class="isActive(item.href) ? 'nav-item-active' : 'nav-item-idle'"
                    >
                        <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                        <span>{{ t(item.label) }}</span>
                    </Link>
                    <div
                        v-else
                        class="nav-item nav-item-idle opacity-50 cursor-not-allowed pointer-events-none"
                        aria-disabled="true"
                    >
                        <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                        <span>{{ t(item.label) }}</span>
                        <span
                            class="ml-auto text-[10px] font-medium nav-coming-soon-badge rounded px-1.5 py-0.5 leading-tight"
                        >
                            {{ t('nav.coming_soon') }}
                        </span>
                    </div>
                </template>
            </nav>

            <!-- Admin section -->
            <div class="mt-4 border-t border-white/[0.06] pt-3 px-2">
                <div
                    class="px-2 pb-1.5 text-[10px] font-semibold uppercase tracking-[0.08em] nav-section-label"
                >
                    {{ t('nav.admin_section') }}
                </div>
                <nav class="flex flex-col gap-0.5">
                    <template v-for="item in adminNavItems" :key="item.key">
                        <Link
                            v-if="item.implemented"
                            :href="item.href"
                            class="nav-item"
                            :class="isActive(item.href) ? 'nav-item-active' : 'nav-item-idle'"
                        >
                            <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                            <span>{{ t(item.label) }}</span>
                        </Link>
                        <div
                            v-else
                            class="nav-item nav-item-idle opacity-50 cursor-not-allowed pointer-events-none"
                            aria-disabled="true"
                        >
                            <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                            <span>{{ t(item.label) }}</span>
                            <span
                                class="ml-auto text-[10px] font-medium nav-coming-soon-badge rounded px-1.5 py-0.5 leading-tight"
                            >
                                {{ t('nav.coming_soon') }}
                            </span>
                        </div>
                    </template>
                </nav>
            </div>

            <div class="flex-1" />
        </aside>

        <!-- Content with content-only transition -->
        <main class="app-content">
            <Transition name="content">
                <div :key="pageComponent">
                    <slot />
                </div>
            </Transition>
        </main>
    </div>

    <!-- Add tenant modal — mounted outside app-shell grid -->
    <AddTenantModal v-model:open="isAddTenantOpen" :colors="tenantColors" @close="isAddTenantOpen = false" />

    <!-- Logout confirm modal -->
    <ConfirmDialog
        :open="isLogoutConfirmOpen"
        :title="t('auth.logout_confirm_title')"
        :body="t('auth.logout_confirm_body')"
        :confirm-label="t('logout')"
        :cancel-label="t('common.cancel')"
        confirm-variant="error"
        @confirm="logout"
        @cancel="isLogoutConfirmOpen = false"
    />
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
        background: var(--color-base-100);
        border-bottom: 1px solid var(--color-base-300);
        z-index: 10;
    }

    .app-sidebar {
        background: var(--color-neutral);
        display: flex;
        flex-direction: column;
        padding: 16px 0;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .app-content {
        background: var(--color-base-200);
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
        color: color-mix(in oklch, var(--color-neutral-content) 60%, transparent);
    }

    .nav-item-idle:hover {
        background: rgba(255, 255, 255, 0.05);
        color: var(--color-neutral-content);
    }

    .nav-item-active {
        background: var(--color-primary);
        color: var(--color-primary-content);
        box-shadow: 0 1px 3px color-mix(in oklch, var(--color-primary) 35%, transparent);
    }

    .nav-item-active:hover {
        background: color-mix(in oklch, var(--color-primary) 80%, black);
    }

    .nav-section-label {
        color: color-mix(in oklch, var(--color-neutral-content) 40%, transparent);
    }

    .nav-coming-soon-badge {
        color: color-mix(in oklch, var(--color-neutral-content) 60%, transparent);
        background: color-mix(in oklch, var(--color-neutral-content) 10%, transparent);
    }

    .dropdown-enter-active,
    .dropdown-leave-active {
        transition:
            opacity 0.15s ease,
            transform 0.15s ease;
    }

    .dropdown-enter-from,
    .dropdown-leave-to {
        opacity: 0;
        transform: translateY(-4px);
    }
</style>
