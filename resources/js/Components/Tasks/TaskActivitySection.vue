<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CommentIcon from '~icons/material-symbols/comment-outline-rounded';
import HistoryIcon from '~icons/material-symbols/history-outline-rounded';
import SortIcon from '~icons/material-symbols/swap-vert-outline-rounded';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ViewModeSwitch from '@/Components/ViewModeSwitch.vue';
import TaskCommentEditForm from './TaskCommentEditForm.vue';
import TaskCommentForm from './TaskCommentForm.vue';
import TaskCommentItem from './TaskCommentItem.vue';
import TaskFeedEvent from './TaskFeedEvent.vue';
import TaskPanelSection from './TaskPanelSection.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { useTaskActivity } from '@/Composables/useTaskActivity';
import type { FeedView } from '@/utils/taskFeed';

const props = defineProps<{
    task: App.Data.Tasks.TaskDetailData;
    thread: App.Data.Tasks.TaskThreadData;
    attachments: App.Data.Tasks.TaskAttachmentListData;
    activity?: App.Data.Tasks.TaskActivityFeedData | null;
    uploading: boolean;
}>();

const { t } = useI18n();

const { view, sort, feed, loading, failed, setView, toggleSort, retry } = useTaskActivity({
    taskId: props.task.id,
    activity: () => props.activity,
    task: () => props.task,
    thread: () => props.thread,
    attachments: () => props.attachments,
});

const viewOptions = computed(() => [
    { value: 'comments', label: t('task_feed_comments'), icon: CommentIcon },
    { value: 'all', label: t('task_feed_all'), icon: HistoryIcon },
]);

function questionState(comment: App.Data.Tasks.TaskCommentData): 'open' | 'answered' | null {
    const question = props.thread.question;

    if (question === null || question.comment_id !== comment.id) {
        return null;
    }

    return question.answered ? 'answered' : 'open';
}

const sortLabel = computed(() => t(sort.value === 'oldest' ? 'task_feed_oldest' : 'task_feed_newest'));

const editingId = ref<string | null>(null);

watch(
    () => props.thread.comments,
    (comments) => {
        if (editingId.value !== null && !comments.some((comment) => comment.id === editingId.value)) {
            editingId.value = null;
        }
    },
);

async function finishEdit(id: string): Promise<void> {
    editingId.value = null;
    await nextTick();
    document.querySelector<HTMLElement>(`#task-comment-${id} [aria-haspopup="menu"]`)?.focus();
}

const deleteConfirm = useDeleteConfirm<App.Data.Tasks.TaskCommentData>({
    resolveUrl: (comment) => `/tasks/${props.task.id}/comments/${comment.id}`,
    getTitle: () => t('task_comment_delete'),
    getDescription: () => t('task_comment_delete_confirm'),
});
</script>

<template>
    <TaskPanelSection :title="t('task_comments')">
        <template #title>
            <ViewModeSwitch
                :model-value="view"
                :options="viewOptions"
                :label="t('task_feed_view')"
                @update:model-value="setView($event as FeedView)"
            />
        </template>
        <template #actions>
            <button
                type="button"
                class="btn btn-ghost btn-sm"
                :aria-label="sortLabel"
                :title="sortLabel"
                @click="toggleSort"
            >
                <SortIcon aria-hidden="true" />
                <span class="hidden sm:inline">{{ sortLabel }}</span>
            </button>
        </template>

        <p v-if="view === 'all' && loading" role="status" class="text-xs text-muted">{{ t('loading') }}</p>
        <div v-else-if="view === 'all' && failed" role="alert" class="flex items-center gap-2 text-sm text-error">
            {{ t('task_feed_load_failed') }}
            <button type="button" class="btn btn-ghost btn-sm" @click="retry">{{ t('retry') }}</button>
        </div>

        <ul v-else class="space-y-4">
            <template
                v-for="item in feed"
                :key="
                    item.type === 'older'
                        ? 'older'
                        : item.type === 'entry'
                          ? `entry-${item.entry.id}`
                          : `comment-${item.comment.id}`
                "
            >
                <li v-if="item.type === 'older'" class="text-xs text-muted">{{ t('task_feed_older_hidden') }}</li>
                <TaskFeedEvent v-else-if="item.type === 'entry'" :entry="item.entry" />
                <TaskCommentItem
                    v-else
                    :comment="item.comment"
                    :question-state="questionState(item.comment)"
                    :uploading="uploading"
                    :editing="editingId === item.comment.id"
                    :can-start-edit="editingId === null"
                    @edit="editingId = item.comment.id"
                    @delete="deleteConfirm.openModal(item.comment)"
                >
                    <template #edit>
                        <TaskCommentEditForm
                            :task-id="task.id"
                            :comment="item.comment"
                            :max-chars="thread.max_body_chars"
                            :disabled="uploading"
                            @done="finishEdit(item.comment.id)"
                        />
                    </template>
                    <TaskCommentForm
                        v-if="thread.question?.comment_id === item.comment.id && thread.question.can_answer"
                        class="mt-3"
                        :task-id="task.id"
                        :max-chars="thread.max_body_chars"
                        :disabled="uploading"
                        :answers-comment-id="item.comment.id"
                    />
                </TaskCommentItem>
            </template>
        </ul>

        <TaskCommentForm
            v-if="thread.can_comment"
            class="pt-2"
            :task-id="task.id"
            :max-chars="thread.max_body_chars"
            :disabled="uploading"
        />
    </TaskPanelSection>

    <ConfirmDeleteModal
        :is-open="deleteConfirm.state.isOpen"
        :title="deleteConfirm.getModalTitle()"
        :description="deleteConfirm.getModalDescription()"
        @cancel="deleteConfirm.closeModal"
        @confirm="deleteConfirm.confirmDelete"
    />
</template>
