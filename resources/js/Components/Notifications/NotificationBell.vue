<script setup lang="ts">
    import { computed } from 'vue';

    import { ref } from 'vue';
    import { router, Link } from '@inertiajs/vue3';
    import { storeToRefs } from 'pinia';
    import { BellIcon } from '@heroicons/vue/24/outline';
    import { useNotificationsStore } from '@/stores/notifications';
    import { useTranslate } from '@/Composables/useTranslate';
    import NotificationItem from './NotificationItem.vue';

    const store = useNotificationsStore();
    const { unreadCount, recent, loading } = storeToRefs(store);
    const { t } = useTranslate();

    // eslint-disable-next-line no-restricted-syntax -- imperative UI toggle: bell dropdown
    const isOpen = ref(false);

    const badgeCount = computed<string>(() => (unreadCount.value > 99 ? '99+' : String(unreadCount.value)));

    const ariaLabel = computed<string>(() => {
        const base = t('notifications.bell_label');
        return unreadCount.value > 0 ? `${base} (${badgeCount.value})` : base;
    });

    function handleSelect(n: App.Data.Notifications.NotificationListItemData): void {
        isOpen.value = false;
        store.markReadLocally(n.id);
        router.post(
            `/notifications/${n.id}/read`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    if (n.url) {
                        router.visit(n.url);
                    }
                },
            },
        );
    }

    function markAllRead(): void {
        router.post(
            '/notifications/read-all',
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    void store.fetchBell();
                },
            },
        );
    }
</script>

<template>
    <div class="relative" @keydown.escape="isOpen = false">
        <button
            type="button"
            class="relative flex h-8 w-8 items-center justify-center rounded-md text-base-content/70 hover:bg-base-200 hover:text-base-content transition"
            :aria-label="ariaLabel"
            :aria-expanded="isOpen"
            aria-haspopup="true"
            @click="isOpen = !isOpen"
        >
            <BellIcon class="h-5 w-5" />
            <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-error px-0.5 text-[10px] font-bold text-error-content border-2 border-base-100"
                aria-hidden="true"
            >
                {{ badgeCount }}
            </span>
        </button>

        <Transition name="bell-dropdown">
            <div
                v-if="isOpen"
                class="absolute right-0 top-full mt-1 z-30 w-80 rounded-lg border border-base-300 bg-base-100 shadow-lg"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-3 py-2 border-b border-base-300">
                    <span class="text-sm font-semibold text-base-content">
                        {{ t('notifications.title') }}
                    </span>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-xs text-primary hover:underline"
                        @click="markAllRead"
                    >
                        {{ t('notifications.mark_all_read') }}
                    </button>
                </div>

                <!-- Loading state (first load only) -->
                <div v-if="loading && !recent.length" class="flex justify-center py-6">
                    <span class="loading loading-spinner loading-sm text-primary" />
                </div>

                <!-- Empty state -->
                <div v-else-if="!recent.length" class="px-3 py-6 text-center">
                    <BellIcon class="h-8 w-8 mx-auto text-base-content/30 mb-2" />
                    <p class="text-sm text-base-content/50">{{ t('notifications.empty') }}</p>
                </div>

                <!-- Notification list -->
                <ul v-else role="menu" class="py-1 max-h-80 overflow-y-auto">
                    <NotificationItem
                        v-for="n in recent"
                        :key="n.id"
                        :notification="n"
                        :compact="true"
                        @select="handleSelect"
                    />
                </ul>

                <!-- Footer -->
                <div class="border-t border-base-300 px-3 py-2">
                    <Link
                        href="/notifications"
                        class="text-xs text-primary hover:underline"
                        @click="isOpen = false"
                    >
                        {{ t('notifications.see_all') }}
                    </Link>
                </div>
            </div>
        </Transition>

        <!-- Click-outside overlay -->
        <div v-if="isOpen" class="fixed inset-0 z-20" @click="isOpen = false" />
    </div>
</template>

<style scoped>
    .bell-dropdown-enter-active,
    .bell-dropdown-leave-active {
        transition:
            opacity 0.15s ease,
            transform 0.15s ease;
    }

    .bell-dropdown-enter-from,
    .bell-dropdown-leave-to {
        opacity: 0;
        transform: translateY(-4px);
    }
</style>
