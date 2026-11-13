<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SmartToyIcon from '~icons/material-symbols/smart-toy-outline-rounded';
import InitialsAvatar from '@/Components/InitialsAvatar.vue';
import StatusChip from '@/Components/StatusChip.vue';
import TaskCommentMenu from './TaskCommentMenu.vue';
import { formatDatetime, formatRelativeTime } from '@/utils/date';

const props = defineProps<{
    comment: App.Data.Tasks.TaskCommentData;
    questionState: 'open' | 'answered' | null;
    uploading: boolean;
    editing: boolean;
    canStartEdit: boolean;
}>();

defineEmits<{ edit: []; delete: [] }>();

const { t, locale } = useI18n();

const isAi = computed(() => props.comment.author_kind === 'ai');
const showEdit = computed(() => props.comment.can_edit && props.canStartEdit && !props.editing);
</script>

<template>
    <li
        :id="`task-comment-${comment.id}`"
        class="scroll-mt-24"
        :class="{ 'rounded-card-nested bg-base-200 p-3': isAi }"
    >
        <div class="flex min-h-8 items-center gap-3">
            <span
                v-if="isAi"
                class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary"
            >
                <SmartToyIcon aria-hidden="true" />
            </span>
            <InitialsAvatar v-else decorative :name="comment.author_name" />

            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-1.5 gap-y-1 text-sm">
                <span class="font-medium">{{ isAi ? t('task_comment_ai_name') : comment.author_name }}</span>
                <span v-if="comment.is_mine" class="text-muted">({{ t('task_comment_you') }})</span>
                <span v-if="isAi" class="text-muted"
                    >· {{ t('task_comment_ai_on_behalf', { name: comment.author_name }) }}</span
                >
                <span class="text-muted">
                    ·
                    <time :datetime="comment.created_at" :title="formatDatetime(comment.created_at)">
                        {{ formatRelativeTime(comment.created_at, new Date(), locale) }}
                    </time>
                </span>
                <span
                    v-if="comment.edited_at"
                    class="text-muted"
                    :title="t('task_comment_edited_at', { date: formatDatetime(comment.edited_at) })"
                >
                    · {{ t('task_comment_edited') }}
                </span>
                <StatusChip
                    v-if="questionState"
                    :tone="questionState === 'open' ? 'warning' : 'done'"
                    :label="t(questionState === 'open' ? 'task_question_open' : 'task_question_answered')"
                    size="sm"
                />
            </div>

            <TaskCommentMenu
                :can-edit="showEdit"
                :can-delete="comment.can_delete"
                :disabled="uploading"
                @edit="$emit('edit')"
                @delete="$emit('delete')"
            />
        </div>

        <div class="ps-9">
            <slot v-if="editing" name="edit" />
            <template v-else>
                <p v-if="comment.answers_comment_id" class="text-xs text-muted">
                    {{ t('task_comment_answer_marker') }}
                </p>
                <p class="whitespace-pre-wrap break-words text-sm">{{ comment.body }}</p>
            </template>

            <slot />
        </div>
    </li>
</template>
