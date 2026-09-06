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
    <!-- Plain div row (not `li`) — DaisyUI's `.menu :where(li) > *` rule forces
         `display:grid; grid-auto-flow:column` on any `li` direct child anywhere inside a
         `.menu`-classed ancestor, which collapses this two-row layout into one row and lets
         the title grow past the dropdown width instead of truncating. Keeping this a `div`
         (parent list is a plain `div`, not `ul`/`menu`) sidesteps that rule entirely. -->
    <div class="w-full min-w-0">
        <button
            type="button"
            role="menuitem"
            class="flex w-full min-w-0 flex-col items-start gap-0.5 px-3 py-2 text-left hover:bg-base-200"
            :class="{ 'bg-primary/5': isUnread }"
            @click="emit('select', notification)"
        >
            <span class="flex w-full min-w-0 items-center gap-2">
                <span class="size-2 shrink-0 rounded-full" :class="dotClass" :title="typeLabel" aria-hidden="true" />
                <span class="sr-only">{{ typeLabel }}</span>
                <time
                    :datetime="notification.created_at"
                    class="ml-auto shrink-0 whitespace-nowrap text-xs text-base-content/50"
                >
                    {{ formatShortDatetime(notification.created_at) }}
                </time>
            </span>
            <span class="flex w-full min-w-0 items-center gap-2">
                <span class="min-w-0 flex-1 truncate text-sm" :class="{ 'font-semibold': isUnread }">
                    {{ notification.title }}
                </span>
                <span v-if="isUnread" class="h-2 w-2 shrink-0 rounded-full bg-primary" aria-hidden="true" />
            </span>
        </button>
    </div>
</template>
