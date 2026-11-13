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
import { useListQueryMemory } from '@/Composables/useListQueryMemory';

type NavigationItem = App.Data.NavigationItemData;
import LogoutIcon from '~icons/material-symbols/logout-outline-rounded';
import LanguageIcon from '~icons/material-symbols/language-outline-rounded';
import { KG_ICONS, NAV_ICONS } from '@/icons/kegosIcons';
import { forgetAllConversations } from '@/utils/assistant';
import { mobileNavItems } from '@/utils/mobileNav';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import InitialsAvatar from '@/Components/InitialsAvatar.vue';
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
const { withRememberedQuery, clear: clearListMemory } = useListQueryMemory();
const drawerOpen = ref(false);
const languageOpen = ref(false);
const bottomNavItems = computed(() => mobileNavItems(navigation.value));
const MoreIcon = KG_ICONS.adminAction.more;
const CaretIcon = KG_ICONS.adminAction.caretDown;

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
        if (userId && companyId) assistant.bind(userId, companyId);
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
    clearListMemory();
    forgetAllConversations();
    router.post('/logout');
}

function isActive(href: string): boolean {
    if (href === '/') {
        return page.url.split('?')[0] === '/' || page.url === '';
    }
    return page.url.startsWith(href);
}

function isGroupActive(item: NavigationItem): boolean {
    return item.children.some((child) => isActive(child.href));
}

function toastAlertClass(type: ToastMessage['type']): string {
    if (type === 'success') return 'alert-success';
    if (type === 'error') return 'alert-error';
    return 'alert-info';
}
</script>

<template>
    <div class="drawer lg:drawer-open" data-theme="app-theme" :data-assistant="canUseAssistant || undefined">
        <input id="app-drawer" v-model="drawerOpen" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content flex min-h-screen flex-col bg-base-200">
            <div class="navbar sticky top-0 z-10 bg-base-200 px-5 lg:hidden">
                <div class="flex-1">
                    <Link href="/" :aria-label="t('app_name')"><BrandLogo :size="18" /></Link>
                </div>
                <div v-if="tenant.available.length > 0" class="flex-none">
                    <TenantSwitcher :tenant="tenant" compact @add-tenant="isAddTenantOpen = true" />
                </div>
                <div v-if="canViewNotifications" class="flex-none">
                    <NotificationBell />
                </div>
            </div>

            <div id="page-header" class="bg-base-200 px-5 pt-5 empty:hidden lg:px-6 lg:pt-6" />

            <main class="flex-1 bg-base-200 p-5 pb-[calc(1.25rem+var(--kg-bottom-nav-clearance))] lg:p-6">
                <slot />
            </main>
        </div>

        <div class="drawer-side z-20">
            <label for="app-drawer" class="drawer-overlay" />
            <aside class="kg-panel flex h-screen w-(--kg-sidebar-width) flex-col overflow-hidden text-base-content">
                <div class="px-5 pb-4 pt-5">
                    <Link href="/" class="inline-block" :aria-label="t('app_name')"><BrandLogo :size="22" /></Link>
                </div>

                <div v-if="tenant.available.length > 0" class="px-3 pb-3">
                    <TenantSwitcher :tenant="tenant" @add-tenant="isAddTenantOpen = true" />
                </div>

                <nav class="flex-1 overflow-y-auto p-3" :aria-label="t('nav_main')">
                    <ul class="flex flex-col gap-1">
                        <template v-for="item in navigation" :key="item.key">
                            <li v-if="item.children.length === 0">
                                <Link
                                    :href="withRememberedQuery(item.href)"
                                    class="flex min-h-(--kg-nav-item-height) items-center gap-3 rounded-pill px-4 text-sm"
                                    :class="
                                        isActive(item.href)
                                            ? 'bg-primary font-medium text-primary-content'
                                            : 'text-base-content hover:bg-neutral'
                                    "
                                    :aria-current="isActive(item.href) ? 'page' : undefined"
                                >
                                    <component
                                        :is="NAV_ICONS[item.icon]"
                                        :class="isActive(item.href) ? '' : 'text-muted'"
                                        aria-hidden="true"
                                    />
                                    {{ translateLabel(item.label) }}
                                </Link>
                            </li>
                            <li v-else>
                                <details class="group" :open="isGroupActive(item)">
                                    <summary
                                        class="flex min-h-(--kg-nav-item-height) cursor-pointer list-none items-center gap-3 rounded-pill px-4 text-sm text-base-content hover:bg-neutral [&::-webkit-details-marker]:hidden"
                                        :class="{ 'font-medium': isGroupActive(item) }"
                                    >
                                        <component :is="NAV_ICONS[item.icon]" class="text-muted" aria-hidden="true" />
                                        {{ translateLabel(item.label) }}
                                        <CaretIcon
                                            class="ml-auto text-muted group-open:rotate-180"
                                            aria-hidden="true"
                                        />
                                    </summary>
                                    <ul class="mt-1 flex flex-col gap-1 ps-4">
                                        <li v-for="child in item.children" :key="child.key">
                                            <Link
                                                :href="withRememberedQuery(child.href)"
                                                class="flex min-h-(--kg-nav-item-height) items-center gap-3 rounded-pill px-4 text-sm"
                                                :class="
                                                    isActive(child.href)
                                                        ? 'bg-primary font-medium text-primary-content'
                                                        : 'text-base-content hover:bg-neutral'
                                                "
                                                :aria-current="isActive(child.href) ? 'page' : undefined"
                                            >
                                                <component
                                                    :is="NAV_ICONS[child.icon]"
                                                    :class="isActive(child.href) ? '' : 'text-muted'"
                                                    aria-hidden="true"
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

                <div class="mt-auto space-y-2 p-3">
                    <div v-if="auth.user" class="flex items-center gap-3">
                        <InitialsAvatar :name="auth.user.name" size="md" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-base-content">{{ auth.user.name }}</p>
                            <p class="truncate text-xs text-muted">{{ auth.user.email }}</p>
                        </div>
                        <NotificationBell v-if="canViewNotifications" />
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <button
                            type="button"
                            class="btn btn-ghost btn-sm justify-start gap-2 text-base-content"
                            @click="logout"
                        >
                            <LogoutIcon />
                            {{ t('logout') }}
                        </button>

                        <AnchoredPopover
                            v-if="languages && languages.length > 1"
                            v-model:open="languageOpen"
                            class="w-40 p-1"
                        >
                            <template #trigger="{ toggle, open: isOpen }">
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-sm justify-start gap-2 text-base-content"
                                    aria-haspopup="menu"
                                    :aria-expanded="isOpen"
                                    :aria-label="t('locale')"
                                    @click="toggle"
                                >
                                    <LanguageIcon />
                                    <span>{{ locale.toUpperCase() }}</span>
                                </button>
                            </template>
                            <ul class="menu w-full p-0" role="menu">
                                <li v-for="lang in languages" :key="lang.value">
                                    <a
                                        :href="`/language/${lang.value}`"
                                        role="menuitem"
                                        :class="{ 'font-medium': locale === lang.value }"
                                        :aria-current="locale === lang.value ? 'true' : undefined"
                                    >
                                        {{ lang.label }}
                                    </a>
                                </li>
                            </ul>
                        </AnchoredPopover>
                    </div>
                </div>
            </aside>
        </div>

        <nav class="kg-bottom-nav" :aria-label="t('nav_mobile')">
            <Link
                v-for="item in bottomNavItems"
                :key="item.key"
                :href="withRememberedQuery(item.href)"
                :aria-label="translateLabel(item.label)"
                :aria-current="isActive(item.href) ? 'page' : undefined"
            >
                <component :is="NAV_ICONS[item.icon]" aria-hidden="true" />
            </Link>
            <button type="button" :aria-label="t('open_menu')" :aria-expanded="drawerOpen" @click="drawerOpen = true">
                <MoreIcon aria-hidden="true" />
            </button>
        </nav>

        <AddTenantModal :open="isAddTenantOpen" :colors="tenantColors" @close="isAddTenantOpen = false" />

        <AssistantLauncher v-if="canUseAssistant" :hidden="drawerOpen" />
    </div>

    <div
        class="toast toast-bottom toast-end z-50"
        :class="[
            canUseAssistant ? 'mb-[calc(var(--kg-bottom-nav-clearance)+5rem)]' : 'mb-(--kg-bottom-nav-clearance)',
            { 'sm:me-[37.5rem]': canUseAssistant && assistant.state.open },
        ]"
    >
        <div v-for="toast in toasts" :key="toast.id" class="alert" :class="toastAlertClass(toast.type)">
            <span>{{ toast.message }}</span>
        </div>
    </div>
</template>
