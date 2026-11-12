<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatShortDatetime } from '@/utils/date';
import { notificationTypeKey } from '@/utils/enums';
import { NOTIFICATION_TYPE_STYLE } from './notificationTypeStyle';

const props = defineProps<{
    notification: App.Data.Notifications.NotificationListItemData;
}>();

const emit = defineEmits<{
    select: [notification: App.Data.Notifications.NotificationListItemData];
}>();

const { t } = useI18n();

const isUnread = computed(() => props.notification.read_at === null);
const typeLabel = computed(() => t(notificationTypeKey(props.notification.type)));
const dotClass = computed(() => NOTIFICATION_TYPE_STYLE[props.notification.type].dot);
</script>

<template>
    <button type="button" class="kg-list-row w-full min-w-0 text-left" @click="emit('select', notification)">
        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="flex w-full min-w-0 items-center gap-2">
                <span class="kg-dot" :class="dotClass" :title="typeLabel" aria-hidden="true" />
                <span class="sr-only">{{ typeLabel }}</span>
                <time :datetime="notification.created_at" class="ml-auto shrink-0 whitespace-nowrap text-xs text-muted">
                    {{ formatShortDatetime(notification.created_at) }}
                </time>
            </span>
            <span class="flex w-full min-w-0 items-center gap-2">
                <span class="min-w-0 flex-1 truncate text-sm" :class="{ 'font-medium': isUnread }">
                    {{ notification.title }}
                </span>
                <span v-if="isUnread" class="kg-dot bg-primary" aria-hidden="true" />
            </span>
        </span>
    </button>
</template>
