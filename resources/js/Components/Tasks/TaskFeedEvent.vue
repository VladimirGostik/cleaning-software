<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import InitialsAvatar from '@/Components/InitialsAvatar.vue';
import { formatDatetime, formatRelativeTime } from '@/utils/date';
import { taskPriorityKey, taskStatusKey } from '@/utils/enums';
import { feedChangeLine, feedEntryHeadline } from '@/utils/taskFeed';
import type { FeedChangeLine } from '@/utils/taskFeed';

const props = defineProps<{ entry: App.Data.Tasks.TaskActivityEntryData }>();

const { t, locale } = useI18n();

const actor = computed(() => props.entry.actor_name ?? t('task_feed_system'));
const headline = computed(() => {
    const { key, params } = feedEntryHeadline(props.entry);

    return t(key, { ...params, name: actor.value });
});
const lines = computed(() => props.entry.changes.map((change) => lineText(feedChangeLine(change))));

function value(line: FeedChangeLine, raw: string | null): string {
    if (line.format === 'assignee') return raw ?? t('task_unassigned');
    if (raw === null) return t('empty_dash');
    if (line.format === 'status') return t(taskStatusKey(raw as App.Enums.TaskStatusEnum));
    if (line.format === 'priority') return t(taskPriorityKey(raw as App.Enums.TaskPriorityEnum));
    if (line.format === 'datetime') return formatDatetime(raw);

    return raw;
}

function lineText(line: FeedChangeLine): string {
    return t(line.key, { field: t(line.fieldKey), from: value(line, line.from), to: value(line, line.to) });
}
</script>

<template>
    <li class="flex gap-3">
        <InitialsAvatar v-if="entry.actor_name" decorative class="-mt-0.5" :name="entry.actor_name" />
        <span v-else class="-mt-0.5 size-6 shrink-0 rounded-full bg-base-200" aria-hidden="true" />

        <div class="min-w-0 flex-1 text-sm">
            <p class="break-words">
                {{ headline }}
                <span class="text-muted">
                    ·
                    <time :datetime="entry.occurred_at" :title="formatDatetime(entry.occurred_at)">
                        {{ formatRelativeTime(entry.occurred_at, new Date(), locale) }}
                    </time>
                </span>
            </p>
            <ul v-if="lines.length > 0" class="text-muted">
                <li v-for="(line, index) in lines" :key="index" class="break-words">{{ line }}</li>
            </ul>
        </div>
    </li>
</template>
