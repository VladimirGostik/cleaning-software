<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BrandLogo from '@/Components/BrandLogo.vue';
import TenantSwitcher from '@/Components/Tenants/TenantSwitcher.vue';
import AddTenantModal from '@/Components/Tenants/AddTenantModal.vue';
import { usePageProps } from '@/Composables/usePageProps';
import { useAuthorization } from '@/Composables/useAuthorization';
import NotificationBell from '@/Components/Notifications/NotificationBell.vue';
import AssistantLauncher from '@/Components/Assistant/AssistantLauncher.vue';
import { useAssistantChat } from '@/Composables/useAssistantChat';

type NavigationItem = App.Data.NavigationItemData;
import MenuIcon from '~icons/material-symbols/menu-rounded';
import LogoutIcon from '~icons/material-symbols/logout-outline-rounded';
import LanguageIcon from '~icons/material-symbols/language-outline-rounded';
import { NAV_ICONS } from '@/icons/kegosIcons';
import type { ToastPayload } from '@/Composables/useToast';

const { t } = useI18n();
const page = usePage();
const props = usePageProps();
const { allows } = useAuthorization();

const auth = computed(() => props.value.auth);
const locale = computed(() => props.value.locale);
const languages = computed(() => props.value.languages);
const navigation = computed(() => props.value.navigation ?? []);
const tenant = computed(() => props.value.tenant);
const tenantColors = computed(() => props.value.tenantColors);
const isAddTenantOpen = ref(false);
const canViewNotifications = computed(() => allows('view notifications'));
const canUseAssistant = computed(() => allows('use assistant'));
const assistant = useAssistantChat();
const drawerOpen = ref(false);

function navIcon(item: NavigationItem, active: boolean) {
    const icons = NAV_ICONS[item.icon];
    return active ? icons.filled : icons.outline;
}

function translateLabel(label: string): string {
    return label.startsWith('app.') ? t(label.slice(4)) : t(label);
}

interface ToastMessage {
    id: number;
    message: string;
    type: 'success' | 'error' | 'info';
}

const toasts = reactive<ToastMessage[]>([]);
let toastCounter = 0;

function addToast(message: string, type: ToastMessage['type']) {
    if (!message) return;
    const id = ++toastCounter;
    toasts.push({ id, message, type });
    setTimeout(() => {
        const index = toasts.findIndex((t) => t.id === id);
        if (index !== -1) toasts.splice(index, 1);
    }, 4000);
}

function handleToastEvent(event: Event) {
    const customEvent = event as CustomEvent<ToastPayload>;
    const { message, type } = customEvent.detail;
    addToast(message, type);
}

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) addToast(flash.success, 'success');
        if (flash?.error) addToast(flash.error, 'error');
        if (flash?.info) addToast(flash.info, 'info');
    },
    { immediate: true, deep: true },
);

watch(
    [() => auth.value.user?.id, () => tenant.value.active?.id],
    ([userId, companyId]) => {
        if (userId && companyId && assistant.bind(userId, companyId)) addToast(t('assistant_discarded'), 'info');
    },
    { immediate: true },
);

let removeNavigate: (() => void) | undefined;

onMounted(() => {
    window.addEventListener('app-toast', handleToastEvent);
    removeNavigate = router.on('navigate', () => {
        drawerOpen.value = false;
    });
});

onUnmounted(() => {
    window.removeEventListener('app-toast', handleToastEvent);
    removeNavigate?.();
    assistant.reset();
    assistant.close();
});

function logout() {
    router.post('/logout');
}

function isActive(href: string): boolean {
    if (href === '/') {
        return page.url.split('?')[0] === '/' || page.url === '';
    }
    return page.url.startsWith(href);
}

function toastAlertClass(type: ToastMessage['type']): string {
    if (type === 'success') return 'alert-success';
    if (type === 'error') return 'alert-error';
    return 'alert-info';
}
</script>

<template>
    <div class="drawer lg:drawer-open" data-theme="app-theme">
        <input id="app-drawer" v-model="drawerOpen" type="checkbox" class="drawer-toggle" />

        <!-- Page content -->
        <div class="drawer-content flex flex-col min-h-screen">
            <!-- Mobile top bar -->
            <div class="navbar bg-base-100 border-b border-base-300 lg:hidden sticky top-0 z-10">
                <div class="flex-none">
                    <label
                        for="app-drawer"
                        class="btn btn-square btn-ghost"
                        :aria-label="t('open_menu')"
                        :title="t('open_menu')"
                    >
                        <MenuIcon class="size-5" />
                    </label>
                </div>
                <div class="flex-1">
                    <Link href="/" :aria-label="t('app_name')"><BrandLogo :size="24" /></Link>
                </div>
                <div v-if="tenant.available.length > 0" class="flex-none">
                    <TenantSwitcher :tenant="tenant" compact @add-tenant="isAddTenantOpen = true" />
                </div>
                <div v-if="canViewNotifications" class="flex-none">
                    <NotificationBell compact />
                </div>
            </div>

            <div id="page-header" class="bg-base-200 px-7 pt-7 empty:hidden" />

            <main class="flex-1 p-7 bg-base-200">
                <slot />
            </main>
        </div>

        <!-- Sidebar -->
        <div class="drawer-side z-20">
            <label for="app-drawer" class="drawer-overlay" />
            <aside
                class="w-64 h-screen overflow-hidden bg-base-100 border-r border-base-300 text-base-content flex flex-col"
            >
                <!-- Brand -->
                <div class="px-4 pt-4 pb-5">
                    <Link href="/" class="inline-block" :aria-label="t('app_name')"><BrandLogo :size="28" /></Link>
                </div>

                <div v-if="tenant.available.length > 0" class="px-3 pb-3">
                    <TenantSwitcher :tenant="tenant" @add-tenant="isAddTenantOpen = true" />
                </div>

                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto p-3">
                    <ul class="menu menu-sm w-full gap-0.5 p-0">
                        <template v-for="item in navigation" :key="item.key">
                            <li v-if="item.children.length === 0">
                                <Link
                                    :href="item.href"
                                    :class="{ active: isActive(item.href) }"
                                    class="flex items-center gap-2"
                                >
                                    <component
                                        :is="navIcon(item, isActive(item.href))"
                                        class="size-5"
                                        :class="isActive(item.href) ? 'text-primary-hover' : 'text-muted'"
                                    />
                                    {{ translateLabel(item.label) }}
                                </Link>
                            </li>
                            <li v-else>
                                <details :open="item.children.some((c: NavigationItem) => isActive(c.href))">
                                    <summary class="flex items-center gap-2">
                                        <component
                                            :is="
                                                navIcon(
                                                    item,
                                                    item.children.some((c: NavigationItem) => isActive(c.href)),
                                                )
                                            "
                                            class="size-5"
                                            :class="
                                                item.children.some((c: NavigationItem) => isActive(c.href))
                                                    ? 'text-primary-hover'
                                                    : 'text-muted'
                                            "
                                        />
                                        {{ translateLabel(item.label) }}
                                    </summary>
                                    <ul>
                                        <li v-for="child in item.children" :key="child.key">
                                            <Link :href="child.href" :class="{ active: isActive(child.href) }">
                                                <component
                                                    :is="navIcon(child, isActive(child.href))"
                                                    class="size-5"
                                                    :class="isActive(child.href) ? 'text-primary-hover' : 'text-muted'"
                                                />
                                                {{ translateLabel(child.label) }}
                                            </Link>
                                        </li>
                                    </ul>
                                </details>
                            </li>
                        </template>
                    </ul>
                </nav>

                <!-- Footer: identity + bell row, logout + language row -->
                <div class="relative mt-auto space-y-2 border-t border-base-300 p-3">
                    <div v-if="auth.user" class="flex items-center gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary-hover"
                        >
                            {{ auth.user.name.charAt(0).toUpperCase() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-base-content">{{ auth.user.name }}</p>
                            <p class="truncate text-xs text-muted">{{ auth.user.email }}</p>
                        </div>
                        <NotificationBell v-if="canViewNotifications" icon-only />
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <button
                            type="button"
                            class="btn btn-ghost btn-sm justify-start gap-2 text-base-content"
                            @click="logout"
                        >
                            <LogoutIcon class="size-5" />
                            {{ t('logout') }}
                        </button>

                        <div v-if="languages && languages.length > 1" class="dropdown dropdown-top dropdown-end">
                            <div
                                tabindex="0"
                                role="button"
                                class="btn btn-ghost btn-sm justify-start gap-2 text-base-content"
                            >
                                <LanguageIcon class="size-5" />
                                <span>{{ locale.toUpperCase() }}</span>
                            </div>
                            <ul
                                tabindex="0"
                                class="dropdown-content menu menu-sm bg-base-100 text-base-content rounded-box shadow-float z-50 w-40 p-1"
                            >
                                <li v-for="lang in languages" :key="lang.value">
                                    <a :href="`/language/${lang.value}`" :class="{ active: locale === lang.value }">
                                        <span v-if="lang.flag">{{ lang.flag }}</span>
                                        {{ lang.label }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <AddTenantModal :open="isAddTenantOpen" :colors="tenantColors" @close="isAddTenantOpen = false" />

        <AssistantLauncher v-if="canUseAssistant" :hidden="drawerOpen" />
    </div>

    <!-- Toast container -->
    <div
        class="toast toast-bottom toast-end z-50"
        :class="{ 'mb-20': canUseAssistant, 'sm:me-[27rem]': canUseAssistant && assistant.state.open }"
    >
        <div v-for="toast in toasts" :key="toast.id" class="alert" :class="toastAlertClass(toast.type)">
            <span>{{ toast.message }}</span>
        </div>
    </div>
</template>

<style scoped>
nav .menu :is(a, summary) {
    font-size: 14px;
    font-weight: 500;
    border-radius: var(--radius-field);
    padding: 8px 10px;
    color: var(--color-base-content);
    transition: all 0.12s;
}
nav .menu :is(a, summary):hover {
    background: var(--color-base-200);
}
nav .menu a.active {
    background: var(--color-primary-soft);
    color: var(--color-primary-hover);
    box-shadow: none;
}
nav .menu a.active:hover {
    background: var(--color-primary-soft);
}
</style>
