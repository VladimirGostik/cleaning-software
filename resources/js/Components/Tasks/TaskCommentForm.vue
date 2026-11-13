<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ArrowUpIcon from '~icons/material-symbols/arrow-upward-outline-rounded';
import InitialsAvatar from '@/Components/InitialsAvatar.vue';
import TaskCommentBox from './TaskCommentBox.vue';
import { runAfterTaskEdits } from '@/Composables/useTaskInlineEdit';
import { useToast } from '@/Composables/useToast';

const props = defineProps<{
    taskId: string;
    maxChars: number;
    disabled: boolean;
    answersCommentId?: string;
}>();

const { t } = useI18n();
const toast = useToast();
const page = usePage<{ auth: { user: { name: string } | null } }>();

const form = useForm<{ body: string; answers_comment_id: string | null }>('post', `/tasks/${props.taskId}/comments`, {
    body: '',
    answers_comment_id: props.answersCommentId ?? null,
});

const isAnswer = computed(() => props.answersCommentId !== undefined);
const userName = computed(() => page.props.auth.user?.name ?? '');
const canSubmit = computed(
    () => !props.disabled && !form.processing && form.body.trim() !== '' && form.body.length <= props.maxChars,
);
const error = computed(() => form.errors.body ?? form.errors.answers_comment_id ?? form.errors.status);

function fail(): boolean {
    toast.error(t('task_comment_failed'));

    return false;
}

function send(): void {
    form.submit({
        only: ['selected_thread', 'flash'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
        onHttpException: fail,
        onNetworkError: fail,
    });
}

function submit(): void {
    if (canSubmit.value) void runAfterTaskEdits(send);
}
</script>

<template>
    <form novalidate class="flex gap-3" @submit.prevent="submit">
        <InitialsAvatar decorative class="kg-composer-avatar" :name="userName" />

        <div class="min-w-0 flex-1">
            <TaskCommentBox
                v-model="form.body"
                :label="t(isAnswer ? 'task_question_answer_label' : 'task_comment_label')"
                :placeholder="t(isAnswer ? 'task_question_answer_placeholder' : 'task_comment_placeholder')"
                :max-chars="maxChars"
                :error="error"
                @submit="submit"
            >
                <template #inside>
                    <button
                        type="submit"
                        class="btn btn-primary btn-circle btn-sm"
                        :disabled="!canSubmit"
                        :aria-label="t(isAnswer ? 'task_question_answer_send' : 'task_comment_send')"
                    >
                        <span v-if="form.processing" class="loading loading-spinner loading-xs" aria-hidden="true" />
                        <ArrowUpIcon v-else aria-hidden="true" />
                    </button>
                </template>
            </TaskCommentBox>
        </div>
    </form>
</template>
