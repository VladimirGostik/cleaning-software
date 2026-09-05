<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

type NavigationItem = App.Data.NavigationItemData;
import {
    HomeIcon,
    ClipboardDocumentListIcon,
    UsersIcon,
    ShieldCheckIcon,
    UserCircleIcon,
    Cog6ToothIcon,
    ArrowRightOnRectangleIcon,
    GlobeAltIcon,
    PhotoIcon,
    EnvelopeIcon,
} from '@heroicons/vue/24/outline';
import type { ToastPayload } from '@/Composables/useToast';

const { t } = useI18n();
const page = usePage();

const appName = (import.meta.env.VITE_APP_NAME as string | undefined) ?? 'App';

const auth = computed(() => page.props.auth);
const locale = computed(() => page.props.locale);
const languages = computed(() => page.props.languages);
const navigation = computed(() => page.props.navigation ?? []);

const ICONS: Record<string, object> = {
    HomeIcon,
    UsersIcon,
    ShieldCheckIcon,
    ClipboardDocumentListIcon,
    PhotoIcon,
    EnvelopeIcon,
    UserCircleIcon,
    Cog6ToothIcon,
};

function resolveIcon(name: string): object {
    return ICONS[name] ?? HomeIcon;
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

onMounted(() => {
    window.addEventListener('app-toast', handleToastEvent);
});

onUnmounted(() => {
    window.removeEventListener('app-toast', handleToastEvent);
});

function logout() {
    router.post('/logout');
}

function isActive(href: string): boolean {
    if (href === '/') {
        return page.url === '/' || page.url === '';
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
    <div
        class="drawer lg:drawer-open"
        data-theme="app-theme"
    >
        <input
            id="app-drawer"
            type="checkbox"
            class="drawer-toggle"
        />

        <!-- Page content -->
        <div class="drawer-content flex flex-col min-h-screen">
            <!-- Mobile top bar -->
            <div
                class="navbar bg-base-100 shadow-sm lg:hidden sticky top-0 z-10"
            >
                <div class="flex-none">
                    <label
                        for="app-drawer"
                        class="btn btn-square btn-ghost"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            class="inline-block size-5 stroke-current"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </label>
                </div>
                <div class="flex-1">
                    <span class="text-lg font-bold">{{ appName }}</span>
                </div>
            </div>

            <!-- Main content -->
            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>

        <!-- Sidebar -->
        <div class="drawer-side z-20">
            <label
                for="app-drawer"
                class="drawer-overlay"
            />
            <aside
                class="w-64 min-h-screen bg-base-200 flex flex-col"
            >
                <!-- Brand -->
                <div class="p-4 border-b border-base-300">
                    <a
                        href="/"
                        class="text-xl font-bold text-primary"
                    >
                        {{ appName }}
                    </a>
                </div>

                <!-- User info -->
                <div
                    v-if="auth.user"
                    class="p-4 border-b border-base-300"
                >
                    <div class="flex items-center gap-3">
                        <div class="avatar placeholder">
                            <div
                                class="bg-neutral text-neutral-content rounded-full size-9 text-center pt-1"
                            >
                                <span class="text-xl">{{
                                    auth.user.name.charAt(0).toUpperCase()
                                }}</span>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium text-sm truncate">
                                {{ auth.user.name }}
                            </p>
                            <p class="text-xs text-base-content/60 truncate">
                                {{ auth.user.email }}
                            </p>
                        </div>
                    </div>
                    <button
                        class="btn btn-sm btn-ghost btn-error mt-2 w-full justify-start gap-2"
                        @click="logout"
                    >
                        <ArrowRightOnRectangleIcon class="size-4" />
                        {{ t('logout') }}
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 p-3">
                    <ul class="menu menu-sm gap-1 p-0">
                        <template
                            v-for="item in navigation"
                            :key="item.key"
                        >
                            <li v-if="item.children.length === 0">
                                <a
                                    :href="item.href"
                                    :class="{ active: isActive(item.href) }"
                                    class="flex items-center gap-2"
                                >
                                    <component
                                        :is="resolveIcon(item.icon)"
                                        class="size-4"
                                    />
                                    {{ translateLabel(item.label) }}
                                </a>
                            </li>
                            <li v-else>
                                <details :open="item.children.some((c: NavigationItem) => isActive(c.href))">
                                    <summary class="flex items-center gap-2">
                                        <component
                                            :is="resolveIcon(item.icon)"
                                            class="size-4"
                                        />
                                        {{ translateLabel(item.label) }}
                                    </summary>
                                    <ul>
                                        <li
                                            v-for="child in item.children"
                                            :key="child.key"
                                        >
                                            <a
                                                :href="child.href"
                                                :class="{ active: isActive(child.href) }"
                                            >
                                                <component
                                                    :is="resolveIcon(child.icon)"
                                                    class="size-4"
                                                />
                                                {{ translateLabel(child.label) }}
                                            </a>
                                        </li>
                                    </ul>
                                </details>
                            </li>
                        </template>
                    </ul>
                </nav>

                <!-- Language switcher -->
                <div
                    v-if="languages && languages.length > 1"
                    class="p-3 border-t border-base-300"
                >
                    <div class="dropdown dropdown-top w-full">
                        <div
                            tabindex="0"
                            role="button"
                            class="btn btn-sm btn-ghost w-full justify-start gap-2"
                        >
                            <GlobeAltIcon class="size-4" />
                            <span>{{ locale.toUpperCase() }}</span>
                        </div>
                        <ul
                            tabindex="0"
                            class="dropdown-content menu menu-sm bg-base-100 rounded-box shadow-lg z-50 w-full p-1"
                        >
                            <li
                                v-for="lang in languages"
                                :key="lang.value"
                            >
                                <a
                                    :href="`/language/${lang.value}`"
                                    :class="{ active: locale === lang.value }"
                                >
                                    <span v-if="lang.flag">{{ lang.flag }}</span>
                                    {{ lang.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- Toast container -->
    <div class="toast toast-bottom toast-end z-50">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="alert"
            :class="toastAlertClass(toast.type)"
        >
            <span>{{ toast.message }}</span>
        </div>
    </div>
</template>
