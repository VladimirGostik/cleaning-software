<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import TaskCommentForm from './TaskCommentForm.vue';
import TaskCommentItem from './TaskCommentItem.vue';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';

const props = defineProps<{
    taskId: string;
    thread: App.Data.Tasks.TaskThreadData;
    uploading: boolean;
}>();

const { t } = useI18n();

function questionState(comment: App.Data.Tasks.TaskCommentData): 'open' | 'answered' | null {
    const question = props.thread.question;

    if (question === null || question.comment_id !== comment.id) {
        return null;
    }

    return question.answered ? 'answered' : 'open';
}

const deleteConfirm = useDeleteConfirm<App.Data.Tasks.TaskCommentData>({
    resolveUrl: (comment) => `/tasks/${props.taskId}/comments/${comment.id}`,
    getTitle: () => t('task_comment_delete'),
    getDescription: () => t('task_comment_delete_confirm'),
});
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('task_comments') }}</h2>

            <p v-if="thread.comments.length === 0" class="text-base-content/60">{{ t('task_comments_empty') }}</p>

            <ul v-else class="space-y-2">
                <TaskCommentItem
                    v-for="comment in thread.comments"
                    :key="comment.id"
                    :comment="comment"
                    :question-state="questionState(comment)"
                    :uploading="uploading"
                    @delete="deleteConfirm.openModal(comment)"
                >
                    <TaskCommentForm
                        v-if="thread.question?.comment_id === comment.id && thread.question.can_answer"
                        class="mt-3"
                        :task-id="taskId"
                        :max-chars="thread.max_body_chars"
                        :disabled="uploading"
                        :answers-comment-id="comment.id"
                    />
                </TaskCommentItem>
            </ul>

            <TaskCommentForm
                v-if="thread.can_comment"
                :task-id="taskId"
                :max-chars="thread.max_body_chars"
                :disabled="uploading"
            />
        </div>
    </div>

    <ConfirmDeleteModal
        :is-open="deleteConfirm.state.isOpen"
        :title="deleteConfirm.getModalTitle()"
        :description="deleteConfirm.getModalDescription()"
        @cancel="deleteConfirm.closeModal"
        @confirm="deleteConfirm.confirmDelete"
    />
</template>
