<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CheckIcon from '~icons/material-symbols/check-outline-rounded';
import ArrowTopRightOnSquareIcon from '~icons/material-symbols/open-in-new-outline-rounded';
import DataTable from '@/Components/DataTable/DataTable.vue';
import StatusChip from '@/Components/StatusChip.vue';
import NotificationTypeBadge from './NotificationTypeBadge.vue';
import { formatDatetime } from '@/utils/date';
import type { Paginator, TableColumn } from '@/types';
import type { FilterConfig } from '@/types/table';

type NotificationRow = App.Data.Notifications.NotificationListItemData;

const props = defineProps<{
    notifications: Paginator<NotificationRow>;
    typeOptions: { value: App.Enums.NotificationTypeEnum; label: string }[];
}>();

const emit = defineEmits<{
    select: [notification: NotificationRow];
    'mark-read': [notification: NotificationRow];
}>();

const { t } = useI18n();

const columns: TableColumn[] = [
    { key: 'created_at', label: t('created_at'), sortable: true, class: 'whitespace-nowrap' },
    { key: 'type', label: t('type') },
    { key: 'title', label: t('notifications_col_message') },
    { key: 'read_at', label: t('status') },
];

const filterDefinitions = computed<FilterConfig[]>(() => [
    {
        property: 'type',
        label: t('type'),
        type: 'select',
        placeholder: t('select_type'),
        defaultOperator: '=',
        options: props.typeOptions,
    },
    {
        property: 'read',
        label: t('notifications_filter_read'),
        type: 'boolean',
        defaultOperator: '=',
    },
]);
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="notifications"
        :filters="filterDefinitions"
        :enable-search="false"
        row-key="id"
        :row-href="(row) => (row as NotificationRow).url"
        :open-row="(row) => emit('select', row as NotificationRow)"
        :reload-only="['notifications', 'filters', 'unreadCount']"
    >
        <template #cell-created_at="{ value }">
            <time :datetime="value as string">{{ formatDatetime(value as string) }}</time>
        </template>

        <template #cell-type="{ row }">
            <NotificationTypeBadge :type="(row as NotificationRow).type" />
        </template>

        <template #cell-title="{ row }">
            <button
                type="button"
                class="text-left link link-hover"
                :class="{ 'font-medium': (row as NotificationRow).read_at === null }"
                @click="emit('select', row as NotificationRow)"
            >
                {{ (row as NotificationRow).title }}
            </button>
            <p class="text-xs text-muted line-clamp-2">{{ (row as NotificationRow).body }}</p>
        </template>

        <template #cell-read_at="{ row }">
            <StatusChip
                :tone="(row as NotificationRow).read_at === null ? 'planned' : 'neutral'"
                :label="t((row as NotificationRow).read_at === null ? 'notifications_unread' : 'notifications_read')"
                size="sm"
            />
        </template>

        <template #buttons="{ row }">
            <Link
                v-if="(row as NotificationRow).url"
                :href="(row as NotificationRow).url!"
                class="btn btn-ghost btn-square"
                :title="t('view')"
                :aria-label="t('view')"
            >
                <ArrowTopRightOnSquareIcon />
            </Link>
            <button
                v-if="(row as NotificationRow).read_at === null"
                type="button"
                class="btn btn-ghost btn-square"
                :title="t('notifications_mark_read')"
                :aria-label="t('notifications_mark_read')"
                @click="emit('mark-read', row as NotificationRow)"
            >
                <CheckIcon />
            </button>
        </template>
    </DataTable>
</template>
