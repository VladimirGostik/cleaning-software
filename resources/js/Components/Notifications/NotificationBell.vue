<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BellIcon from '~icons/material-symbols/notifications-outline-rounded';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import { useNotificationBell } from '@/Composables/useNotificationBell';
import NotificationItem from './NotificationItem.vue';

const { t } = useI18n();
const { state, refresh, markReadLocally, markAllReadLocally } = useNotificationBell();

const open = ref(false);

const badgeText = computed(() => (state.unreadCount > 99 ? '99+' : String(state.unreadCount)));

const ariaLabel = computed(() =>
    state.unreadCount > 0
        ? t('notifications_bell_unread', { count: state.unreadCount })
        : t('notifications_bell_label'),
);

function select(notification: App.Data.Notifications.NotificationListItemData): void {
    open.value = false;
    markReadLocally(notification.id);
    router.post(
        `/notifications/${notification.id}/read`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (notification.url) router.visit(notification.url);
            },
        },
    );
}

function markAllRead(): void {
    open.value = false;
    markAllReadLocally();
    router.post(
        '/notifications/read-all',
        {},
        { preserveScroll: true, preserveState: true, onSuccess: () => void refresh() },
    );
}
</script>

<template>
    <AnchoredPopover
        v-model:open="open"
        role="dialog"
        :aria-label="t('notifications')"
        class="w-80 max-w-[calc(100vw-1rem)]"
    >
        <template #trigger="{ toggle, open: isOpen }">
            <button
                type="button"
                class="btn btn-square btn-ghost text-base-content"
                aria-haspopup="dialog"
                :aria-expanded="isOpen"
                :aria-label="ariaLabel"
                @click="toggle"
            >
                <span class="indicator">
                    <BellIcon />
                    <span
                        v-if="state.unreadCount > 0"
                        class="indicator-item kg-count kg-count--alert"
                        aria-hidden="true"
                    >
                        {{ badgeText }}
                    </span>
                </span>
            </button>
        </template>

        <div class="flex min-w-0 items-center justify-between gap-2 px-4 py-3">
            <span class="font-medium">{{ t('notifications') }}</span>
            <button v-if="state.unreadCount > 0" type="button" class="link link-primary text-xs" @click="markAllRead">
                {{ t('notifications_mark_all_read') }}
            </button>
        </div>

        <div v-if="state.loading" class="px-4 py-4 text-center">
            <span class="loading loading-spinner loading-sm" />
        </div>

        <div v-else-if="state.error && state.recent.length === 0" class="flex flex-col items-center gap-2 px-4 py-4">
            <p class="text-xs text-error">{{ t('notifications_load_failed') }}</p>
            <button type="button" class="btn btn-ghost btn-sm" @click="refresh">{{ t('retry') }}</button>
        </div>

        <div v-else-if="state.recent.length === 0" class="px-4 py-4 text-center">
            <p class="text-muted">{{ t('notifications_empty') }}</p>
        </div>

        <div v-else class="kg-list max-h-[50vh] overflow-y-auto px-2 pb-2">
            <NotificationItem v-for="n in state.recent" :key="n.id" :notification="n" @select="select" />
        </div>

        <div class="px-4 pb-3 pt-1">
            <Link href="/notifications" class="link link-primary text-xs" @click="open = false">
                {{ t('notifications_see_all') }}
            </Link>
        </div>
    </AnchoredPopover>
</template>
