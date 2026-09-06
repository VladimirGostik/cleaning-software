<script setup lang="ts">
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { BellIcon } from '@heroicons/vue/24/outline';
import { useNotificationBell } from '@/Composables/useNotificationBell';
import NotificationItem from './NotificationItem.vue';

defineProps<{ compact?: boolean; iconOnly?: boolean }>();

const { t } = useI18n();
const { state, refresh, markReadLocally, markAllReadLocally } = useNotificationBell();

const badgeText = computed(() => (state.unreadCount > 99 ? '99+' : String(state.unreadCount)));

const ariaLabel = computed(() =>
    state.unreadCount > 0
        ? t('notifications_bell_unread', { count: state.unreadCount })
        : t('notifications_bell_label'),
);

function close(): void {
    if (document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
    }
}

function select(notification: App.Data.Notifications.NotificationListItemData): void {
    close();
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
    close();
    markAllReadLocally();
    router.post(
        '/notifications/read-all',
        {},
        { preserveScroll: true, preserveState: true, onSuccess: () => void refresh() },
    );
}
</script>

<template>
    <div class="dropdown" :class="compact ? 'dropdown-end' : iconOnly ? 'dropdown-top static' : 'dropdown-top w-full'">
        <div
            tabindex="0"
            role="button"
            aria-haspopup="menu"
            :aria-label="ariaLabel"
            class="text-neutral-content/70 transition hover:text-white"
            :class="
                compact
                    ? 'btn btn-sm btn-ghost btn-square hover:bg-white/5'
                    : iconOnly
                      ? 'relative flex size-9 items-center justify-center rounded-lg hover:bg-white/5'
                      : 'btn btn-sm btn-ghost w-full justify-start gap-2 hover:bg-white/5'
            "
        >
            <span class="indicator">
                <BellIcon class="size-4" />
                <span v-if="state.unreadCount > 0" class="indicator-item badge badge-error badge-xs" aria-hidden="true">
                    {{ badgeText }}
                </span>
            </span>
            <span v-if="!compact && !iconOnly">{{ t('notifications_bell_label') }}</span>
        </div>

        <!-- Plain `div` shell, not DaisyUI `.menu` — `.menu :where(li) > *` forces
             `display:grid; grid-auto-flow:column` on any nested `li` direct child anywhere in
             the subtree, which broke row truncation/width. Panel positioning per variant:
             `w-80` fixed for the compact navbar bell (dropdown-end handles alignment); for the
             icon-only sidebar-footer bell the trigger is only `size-9`, so a `dropdown-end`
             panel anchored to it overflows the sidebar's left edge — instead the `.dropdown`
             root is `static` so its containing block becomes the footer (which is `relative`
             in AppLayout.vue), and the panel is `absolute inset-x-0 bottom-full` so its left/
             right edges align with the footer's own box (= the full sidebar width) rather than
             the trigger, opening above row 1 regardless of trigger position; `w-full max-w-full`
             for the (unused today) full-width label variant so it never exceeds the parent
             `.dropdown`'s (already `w-full`) resolved width. -->
        <div
            tabindex="0"
            role="menu"
            class="dropdown-content min-w-0 rounded-box bg-base-100 p-0 text-base-content shadow-lg z-50"
            :class="compact ? 'w-80' : iconOnly ? 'absolute inset-x-0 bottom-full mb-2 w-auto' : 'w-full max-w-full'"
        >
            <div class="flex min-w-0 items-center justify-between gap-2 px-3 py-2">
                <span class="font-semibold">{{ t('notifications') }}</span>
                <button
                    v-if="state.unreadCount > 0"
                    type="button"
                    class="link link-primary text-xs"
                    @click="markAllRead"
                >
                    {{ t('notifications_mark_all_read') }}
                </button>
            </div>

            <div v-if="state.loading" class="px-3 py-4 text-center">
                <span class="loading loading-spinner loading-sm" />
            </div>

            <div
                v-else-if="state.error && state.recent.length === 0"
                class="flex flex-col items-center gap-2 px-3 py-4"
            >
                <p class="text-error text-xs">{{ t('notifications_load_failed') }}</p>
                <button type="button" class="btn btn-ghost btn-xs" @click="refresh">{{ t('retry') }}</button>
            </div>

            <div v-else-if="state.recent.length === 0" class="px-3 py-4 text-center">
                <p class="text-base-content/50">{{ t('notifications_empty') }}</p>
            </div>

            <div
                v-else
                class="flex w-full min-w-0 flex-col overflow-y-auto"
                :class="compact ? 'max-h-80' : 'max-h-[50vh]'"
            >
                <NotificationItem v-for="n in state.recent" :key="n.id" :notification="n" @select="select" />
            </div>

            <div class="border-t border-base-300 px-3 py-2">
                <Link href="/notifications" class="link link-primary text-xs" @click="close">
                    {{ t('notifications_see_all') }}
                </Link>
            </div>
        </div>
    </div>
</template>
