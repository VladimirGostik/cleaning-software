<script setup lang="ts">
    import { computed } from 'vue';
    import { useLocalizedDate } from '@/Composables/useLocalizedDate';
    import NotificationTypeBadge from './NotificationTypeBadge.vue';

    const props = withDefaults(
        defineProps<{
            notification: App.Data.Notifications.NotificationListItemData;
            compact?: boolean;
        }>(),
        {
            compact: false,
        },
    );

    const emit = defineEmits<{
        select: [n: App.Data.Notifications.NotificationListItemData];
    }>();

    const { formatDate } = useLocalizedDate();

    const isUnread = computed(() => !props.notification.readAt);
</script>

<template>
    <li>
        <button
            type="button"
            role="menuitem"
            class="w-full text-left px-3 py-2.5 rounded-md transition hover:bg-base-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :class="{ 'bg-primary/5': isUnread }"
            @click="emit('select', notification)"
        >
            <div class="flex items-start gap-2">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                        <NotificationTypeBadge :type="notification.type" />
                        <span class="text-xs text-base-content/50 ml-auto flex-shrink-0">
                            {{ formatDate(notification.createdAt) }}
                        </span>
                    </div>
                    <p class="text-sm font-medium text-base-content leading-snug">
                        {{ notification.title }}
                    </p>
                    <p
                        class="text-xs text-base-content/60 mt-0.5"
                        :class="{ 'line-clamp-1': compact, 'line-clamp-2': !compact }"
                    >
                        {{ notification.body }}
                    </p>
                </div>
                <span
                    v-if="isUnread"
                    class="flex-shrink-0 mt-1 h-2 w-2 rounded-full bg-primary"
                    aria-hidden="true"
                />
            </div>
        </button>
    </li>
</template>
