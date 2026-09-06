<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { BellIcon } from '@heroicons/vue/24/outline';
import Header from '@/Layouts/Header.vue';
import EmptyState from '@/Components/EmptyState.vue';
import NotificationList from '@/Components/Notifications/NotificationList.vue';
import { useNotificationBell } from '@/Composables/useNotificationBell';
import { readSpatieQuery } from '@/Composables/useSpatieTableQuery';
import type { Breadcrumb, Paginator } from '@/types';

type NotificationRow = App.Data.Notifications.NotificationListItemData;

const props = defineProps<{
    notifications: Paginator<NotificationRow>;
    filters?: Record<string, unknown>;
    typeOptions: { value: App.Enums.NotificationTypeEnum; label: string }[];
    unreadCount: number;
}>();

const { t } = useI18n();
const bell = useNotificationBell();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('notifications') }];

const query = ref(readSpatieQuery());
watch(
    () => props.notifications,
    () => {
        query.value = readSpatieQuery();
    },
);

const hasActiveFilters = computed(() => Object.keys(query.value.filters).length > 0);
const showEmptyState = computed(() => props.notifications.total === 0 && !hasActiveFilters.value);

function select(notification: NotificationRow): void {
    bell.markReadLocally(notification.id);
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

function markRead(notification: NotificationRow): void {
    bell.markReadLocally(notification.id);
    router.post(`/notifications/${notification.id}/read`, {}, { preserveScroll: true, preserveState: true });
}

function markAllRead(): void {
    bell.markAllReadLocally();
    router.post('/notifications/read-all', {}, { preserveScroll: true });
}
</script>

<template>
    <Header :title="t('notifications')" :breadcrumbs="breadcrumbs">
        <template #actions>
            <span v-if="unreadCount > 0" class="badge badge-primary">
                {{ t('notifications_unread_count', { count: unreadCount }) }}
            </span>
            <button type="button" class="btn btn-sm btn-ghost" :disabled="unreadCount === 0" @click="markAllRead">
                {{ t('notifications_mark_all_read') }}
            </button>
        </template>
    </Header>

    <p class="mb-6 text-base-content/60">{{ t('notifications_subtitle') }}</p>

    <EmptyState
        v-if="showEmptyState"
        :title="t('notifications_empty')"
        :description="t('notifications_empty_hint')"
        :icon="BellIcon"
    />

    <div v-else class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <NotificationList
                :notifications="notifications"
                :type-options="typeOptions"
                @select="select"
                @mark-read="markRead"
            />
        </div>
    </div>
</template>
