<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import SmartToyIcon from '~icons/material-symbols/smart-toy-outline-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import StatusChip from '@/Components/StatusChip.vue';
import { formatDatetime } from '@/utils/date';

defineProps<{
    comment: App.Data.Tasks.TaskCommentData;
    questionState: 'open' | 'answered' | null;
    uploading: boolean;
}>();

defineEmits<{ delete: [] }>();

const { t } = useI18n();
</script>

<template>
    <li
        :id="`task-comment-${comment.id}`"
        class="scroll-mt-24 rounded-card-nested p-3"
        :class="{ 'bg-base-200': comment.author_kind === 'ai' }"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <SmartToyIcon v-if="comment.author_kind === 'ai'" class="shrink-0" aria-hidden="true" />
                <span class="font-medium">
                    {{
                        comment.author_kind === 'ai'
                            ? t('task_comment_ai_author', { name: comment.author_name })
                            : comment.author_name
                    }}
                </span>
                <time class="text-muted" :datetime="comment.created_at">
                    {{ formatDatetime(comment.created_at) }}
                </time>
                <StatusChip
                    v-if="questionState"
                    :tone="questionState === 'open' ? 'warning' : 'done'"
                    :label="t(questionState === 'open' ? 'task_question_open' : 'task_question_answered')"
                    size="sm"
                />
            </div>

            <button
                v-if="comment.can_delete"
                type="button"
                class="btn btn-ghost btn-square text-error"
                :disabled="uploading"
                :aria-label="t('task_comment_delete')"
                @click="$emit('delete')"
            >
                <TrashIcon />
            </button>
        </div>

        <p v-if="comment.answers_comment_id" class="mt-1 text-xs text-muted">
            {{ t('task_comment_answer_marker') }}
        </p>
        <p class="mt-1 whitespace-pre-wrap break-words">{{ comment.body }}</p>

        <slot />
    </li>
</template>
