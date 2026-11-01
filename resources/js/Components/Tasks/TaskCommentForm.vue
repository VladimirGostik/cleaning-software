<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import TextareaInput from '@/Components/Forms/TextareaInput.vue';
import { useToast } from '@/Composables/useToast';

const props = defineProps<{
    taskId: string;
    maxChars: number;
    disabled: boolean;
    answersCommentId?: string;
}>();

const { t } = useI18n();
const toast = useToast();

const form = useForm<{ body: string; answers_comment_id: string | null }>('post', `/tasks/${props.taskId}/comments`, {
    body: '',
    answers_comment_id: props.answersCommentId ?? null,
});

const isAnswer = computed(() => props.answersCommentId !== undefined);
const length = computed(() => form.body.length);
const canSubmit = computed(
    () => !props.disabled && !form.processing && form.body.trim() !== '' && length.value <= props.maxChars,
);
const error = computed(() => form.errors.body ?? form.errors.answers_comment_id ?? form.errors.status);

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    form.submit({
        only: ['thread', 'flash'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
        onHttpException: () => {
            toast.error(t('task_comment_failed'));

            return false;
        },
        onNetworkError: () => {
            toast.error(t('task_comment_failed'));

            return false;
        },
    });
}
</script>

<template>
    <form novalidate class="space-y-2" @submit.prevent="submit">
        <TextareaInput
            v-model="form.body"
            :label="t(isAnswer ? 'task_question_answer_label' : 'task_comment_label')"
            :error="error"
            :rows="3"
        />
        <div class="flex items-center justify-between gap-3">
            <span class="text-xs" :class="length > maxChars ? 'text-error' : 'text-base-content/60'">
                {{ t('task_comment_chars', { count: length, max: maxChars }) }}
            </span>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="!canSubmit">
                {{ t(isAnswer ? 'task_question_answer_send' : 'task_comment_send') }}
            </button>
        </div>
    </form>
</template>
