<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import TaskCommentBox from './TaskCommentBox.vue';
import { runAfterTaskEdits } from '@/Composables/useTaskInlineEdit';
import { useToast } from '@/Composables/useToast';

const props = defineProps<{
    taskId: string;
    comment: App.Data.Tasks.TaskCommentData;
    maxChars: number;
    disabled: boolean;
}>();

const emit = defineEmits<{ done: [] }>();

const { t } = useI18n();
const toast = useToast();

const form = useForm<{ body: string }>('put', `/tasks/${props.taskId}/comments/${props.comment.id}`, {
    body: props.comment.body,
});

const canSave = computed(
    () =>
        !props.disabled &&
        !form.processing &&
        form.body.trim() !== '' &&
        form.body !== props.comment.body &&
        form.body.length <= props.maxChars,
);

function fail(): boolean {
    toast.error(t('task_comment_failed'));

    return false;
}

function send(): void {
    form.submit({
        only: ['selected_thread', 'flash'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => emit('done'),
        onHttpException: fail,
        onNetworkError: fail,
    });
}

function save(): void {
    if (canSave.value) void runAfterTaskEdits(send);
}
</script>

<template>
    <form novalidate class="mt-1 space-y-2" @submit.prevent="save">
        <TaskCommentBox
            v-model="form.body"
            :label="t('task_comment_edit_label')"
            :placeholder="t('task_comment_placeholder')"
            :max-chars="maxChars"
            :error="form.errors.body"
            autofocus
            cancellable
            @submit="save"
            @cancel="emit('done')"
        />
        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-ghost btn-sm" @click="emit('done')">{{ t('cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="!canSave">{{ t('save') }}</button>
        </div>
    </form>
</template>
