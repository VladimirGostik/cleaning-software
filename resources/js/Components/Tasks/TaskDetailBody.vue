<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowPathIcon from '~icons/material-symbols/autorenew-outline-rounded';
import CheckIcon from '~icons/material-symbols/check-outline-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import TaskCompanyMenu from '@/Components/Tasks/TaskCompanyMenu.vue';
import TaskDetailSections from '@/Components/Tasks/TaskDetailSections.vue';
import TaskPropertyRow from '@/Components/Tasks/TaskPropertyRow.vue';
import TaskSaveIndicator from '@/Components/Tasks/TaskSaveIndicator.vue';
import TaskStatusMenu from '@/Components/Tasks/TaskStatusMenu.vue';
import TaskTitleField from '@/Components/Tasks/TaskTitleField.vue';

import type { TaskCompanyOption, TaskCreateSession } from '@/Composables/useTaskCreate';
import { flushActiveTaskEdit, taskEditIdle, useTaskInlineEdit, type SaveState } from '@/Composables/useTaskInlineEdit';
import { useTaskTransition } from '@/Composables/useTaskTransition';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        task: App.Data.Tasks.TaskDetailData | null;
        attachments: App.Data.Tasks.TaskAttachmentListData | null;
        context: App.Data.Tasks.TaskFormContextData;
        thread: App.Data.Tasks.TaskThreadData | null;
        activity?: App.Data.Tasks.TaskActivityFeedData | null;
        creator?: TaskCreateSession | null;
    }>(),
    { creator: null },
);

const { processingId, transition } = useTaskTransition();
const edit = useTaskInlineEdit({ taskId: () => props.task?.id ?? props.creator?.createdId.value ?? null });

const uploading = ref(false);
const processing = computed(() => processingId.value !== null || uploading.value);

const titleField = useTemplateRef<{ focus: () => void }>('titleField');
const sections = useTemplateRef<{ focusDescription: () => void }>('sections');

const draftPhase = computed(() => props.creator !== null && props.creator.createdId.value === null);
const canUpdate = computed(() => (props.task ? props.task.can.update : true));
const discardable = computed(
    () => props.task === null && (props.creator?.state.value === 'drafting' || props.creator?.state.value === 'error'),
);
const readOnlyAfterCreate = computed(
    () =>
        props.creator !== null &&
        props.creator.createdId.value !== null &&
        props.task !== null &&
        !props.task.can.update,
);

const firmOptions = computed<TaskCompanyOption[]>(() => {
    if (!props.task) return props.creator?.companyOptions.value ?? [];

    return [
        {
            value: props.task.tenant_id,
            label: props.task.is_global ? t('task_global') : (props.task.tenant_name ?? t('empty_dash')),
        },
    ];
});
const firmValue = computed(() => (props.task ? props.task.tenant_id : (props.creator?.draft.tenantId ?? null)));
const firmLocked = computed(() => props.task !== null || (props.creator?.firmLocked.value ?? true));

const hint = computed(() => {
    if (!discardable.value || !props.creator) return null;

    return props.creator.autoCreate.value ? t('task_new_hint') : t('task_new_hint_enter');
});

const indicatorState = computed<SaveState>(() => {
    const creator = props.creator;

    if (draftPhase.value && creator) {
        if (creator.state.value === 'creating') return 'saving';

        return creator.state.value === 'error' ? 'error' : 'idle';
    }

    if (edit.state.value !== 'idle') return edit.state.value;

    return creator?.createdId.value ? 'saved' : 'idle';
});

const { state, openModal, closeModal, confirmDelete, getModalTitle, getModalDescription } =
    useDeleteConfirm<App.Data.Tasks.TaskDetailData>({
        resolveUrl: (task) => `/tasks/${task.id}`,
        getTitle: () => t('task_delete'),
        getDescription: (task) => t('task_delete_confirm', { title: task.title }),
    });

async function deleteAfterFlush(): Promise<void> {
    if (!taskEditIdle()) await flushActiveTaskEdit();
    confirmDelete();
}

const hintId = useId();
const pendingTitle = ref<string | null>(null);
let titleToSend = false;
let focusDescriptionOnArrival = false;

function applyPendingTitle(): void {
    if (!titleToSend || !props.task || pendingTitle.value === null) return;

    titleToSend = false;

    if (props.task.can.update) edit.save({ title: pendingTitle.value });
    else pendingTitle.value = null;
}

const unregister = props.creator?.onCreated((title) => {
    pendingTitle.value = title;
    titleToSend = title !== null;
    applyPendingTitle();
});

watch(() => props.task, applyPendingTitle);
watch(
    () => props.task?.title,
    (title) => {
        if (title === pendingTitle.value) pendingTitle.value = null;
    },
);
watch(
    () => edit.state.value,
    (value) => {
        if (value === 'saved') pendingTitle.value = null;
    },
);

watch(
    sections,
    async (value) => {
        if (!value || !focusDescriptionOnArrival) return;

        focusDescriptionOnArrival = false;
        await nextTick();
        value.focusDescription();
    },
    { flush: 'post' },
);

onMounted(() => {
    if (props.creator) titleField.value?.focus();
});

onBeforeUnmount(() => unregister?.());

function onTitleInput(value: string): void {
    if (draftPhase.value) {
        props.creator?.type(value);
        return;
    }

    pendingTitle.value = null;

    const title = value.trim();
    if (title !== '') edit.saveDebounced({ title });
}

function onTitleSave(value: string): void {
    if (!draftPhase.value) edit.save({ title: value });
}

function onTitleEnter(): void {
    const creator = props.creator;

    if (draftPhase.value && creator) {
        if (creator.draft.title.trim() === '') return;

        focusDescriptionOnArrival = true;
        creator.createNow();
        return;
    }

    sections.value?.focusDescription();
}

function onFirmMenu(open: boolean): void {
    const creator = props.creator;
    if (!creator) return;

    if (open) {
        creator.holdPause();
        return;
    }

    creator.releasePause();
    titleField.value?.focus();
}

function onFirmChange(value: string | null): void {
    props.creator?.setCompany(value);
}
</script>

<template>
    <div class="space-y-6 px-6 pb-6">
        <div class="flex items-center gap-2">
            <button
                v-if="task && task.can.update && task.is_active"
                type="button"
                class="btn btn-primary"
                :disabled="processing"
                @click="transition(task.id, 'complete')"
            >
                <CheckIcon />
                {{ t('task_complete') }}
            </button>

            <button
                v-if="task && task.can.update && !task.is_active"
                type="button"
                class="btn btn-ghost"
                :disabled="processing"
                @click="transition(task.id, 'reopen')"
            >
                <ArrowPathIcon />
                {{ t('task_reopen') }}
            </button>

            <TaskSaveIndicator class="ms-auto" :state="indicatorState" />

            <button v-if="discardable" type="button" class="btn btn-ghost" @click="creator?.discard()">
                {{ t('task_discard') }}
            </button>

            <button
                v-if="task && task.can.delete"
                type="button"
                class="btn btn-ghost btn-square"
                :disabled="uploading"
                :aria-label="t('delete')"
                :title="t('delete')"
                @click="openModal(task)"
            >
                <TrashIcon />
            </button>
        </div>

        <div class="space-y-3">
            <dl class="text-sm">
                <TaskPropertyRow :label="t('task_company')">
                    <TaskCompanyMenu
                        :model-value="firmValue"
                        :options="firmOptions"
                        :locked="firmLocked"
                        :locked-label="t('task_company_locked')"
                        @update:model-value="onFirmChange"
                        @update:open="onFirmMenu"
                    />
                    <p v-if="creator?.errors.value.tenant_id" class="text-sm text-error">
                        {{ creator.errors.value.tenant_id }}
                    </p>
                </TaskPropertyRow>
            </dl>

            <TaskTitleField
                ref="titleField"
                :key="`title-${edit.resets.value}`"
                :title="pendingTitle ?? task?.title ?? creator?.draft.title ?? ''"
                :editable="canUpdate"
                :disabled="uploading"
                :live="creator !== null"
                :describedby="hint ? hintId : undefined"
                :error="creator?.errors.value.title ?? edit.errors.value.title"
                @input="onTitleInput"
                @enter="onTitleEnter"
                @save="onTitleSave"
            />

            <p v-if="hint" :id="hintId" class="text-sm text-muted">{{ hint }}</p>

            <p v-if="draftPhase && creator?.errors.value.general" class="text-sm text-error" role="alert">
                {{ creator.errors.value.general }}
            </p>

            <dl v-if="draftPhase && creator?.draft.status" class="text-sm">
                <TaskPropertyRow :label="t('status')">
                    <TaskStatusMenu variant="chip" :status="creator.draft.status" :editable="false" />
                </TaskPropertyRow>
            </dl>

            <p v-if="readOnlyAfterCreate" class="text-sm text-muted">{{ t('task_created_read_only') }}</p>
        </div>

        <TaskDetailSections
            v-if="task && attachments && thread"
            ref="sections"
            :key="task.id"
            :task="task"
            :attachments="attachments"
            :context="context"
            :thread="thread"
            :activity="activity"
            :edit="edit"
            @update:uploading="uploading = $event"
        />
    </div>

    <ConfirmDeleteModal
        :is-open="state.isOpen"
        :title="getModalTitle()"
        :description="getModalDescription()"
        @cancel="closeModal"
        @confirm="deleteAfterFlush"
    />
</template>
