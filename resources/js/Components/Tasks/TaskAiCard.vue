<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SmartToyIcon from '~icons/material-symbols/smart-toy-outline-rounded';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
import TaskAiRunBadge from './TaskAiRunBadge.vue';
import TaskPanelSection from './TaskPanelSection.vue';
import { useTenantSwitchVisit } from '@/Composables/useTenantSwitchVisit';
import { formatDatetime } from '@/utils/date';
import { defaultRunCompanyId, earliestPendingExpiry, taskRunActionKind, taskRunStatusTextKey } from '@/utils/taskAi';

const props = defineProps<{
    thread: App.Data.Tasks.TaskThreadData;
    isGlobal: boolean;
    uploading: boolean;
    starting: boolean;
    errors: Record<string, string>;
    statusCheckFailed: boolean;
}>();

const emit = defineEmits<{ start: [companyId: string | null] }>();

const { t } = useI18n();
const { navigate } = useTenantSwitchVisit();

const ACTION_LABEL = { start: 'task_ai_start', continue: 'task_ai_continue', start_again: 'task_ai_start_again' };

const run = computed(() => props.thread.latest_run);
const kind = computed(() => taskRunActionKind(props.thread));
const continueUnanswered = computed(() => kind.value === 'continue' && props.thread.question?.answered !== true);
const needsCompany = computed(() => props.thread.run_companies.length > 0);
const showQuestionLink = computed(() => run.value?.status === 'needs_input' && props.thread.question !== null);
const canStart = computed(() => kind.value !== null && props.thread.ai_available);
const pendingExpiry = computed(() => earliestPendingExpiry(props.thread));
const alertMessage = computed(() => props.errors.ai ?? props.errors.status ?? props.errors.request ?? null);

const pickedCompanyId = ref<string | null>(null);
const companyId = computed(() => {
    const picked = pickedCompanyId.value;

    return picked !== null && props.thread.run_companies.some((company) => company.id === picked)
        ? picked
        : defaultRunCompanyId(props.thread);
});
const companyOptions = computed<SelectOption[]>(() => [
    { value: '', label: t('task_ai_company_choose') },
    ...props.thread.run_companies.map((company) => ({ value: company.id, label: company.name })),
]);

const canPress = computed(
    () => !props.starting && !props.uploading && (!needsCompany.value || companyId.value !== null),
);

function scrollToQuestion(): void {
    const id = props.thread.question?.comment_id;

    document.getElementById(`task-comment-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function press(): void {
    emit('start', needsCompany.value ? companyId.value : null);
}
</script>

<template>
    <TaskPanelSection :title="t('task_ai_title')">
        <template v-if="run" #meta>
            <TaskAiRunBadge :status="run.status" />
        </template>
        <template #actions>
            <button v-if="showQuestionLink" type="button" class="btn btn-ghost btn-sm" @click="scrollToQuestion">
                {{ t('task_ai_go_to_question') }}
            </button>
            <button
                v-if="canStart && kind"
                type="button"
                class="btn btn-ghost btn-sm"
                :disabled="!canPress"
                @click="press"
            >
                <span v-if="starting" class="loading loading-spinner loading-xs" aria-hidden="true" />
                <SmartToyIcon v-else aria-hidden="true" />
                {{ t(ACTION_LABEL[kind]) }}
            </button>
        </template>

        <template v-if="run">
            <p
                class="flex items-center gap-2"
                :role="run.is_active ? 'status' : run.status === 'failed' ? 'alert' : undefined"
            >
                <span v-if="run.is_active" class="loading loading-spinner loading-sm" aria-hidden="true" />
                {{ t(taskRunStatusTextKey(run)) }}
            </p>
            <p class="text-sm text-muted">
                {{ t('task_ai_started_by', { name: run.started_by_name }) }}
                <template v-if="isGlobal"> · {{ run.tenant_name }}</template>
            </p>

            <div v-if="run.actions.length > 0">
                <h3 class="text-sm font-medium">{{ t('task_ai_actions_title') }}</h3>
                <ul class="mt-1 list-inside list-disc text-sm">
                    <li v-for="(action, index) in run.actions" :key="index">
                        <button
                            v-if="action.url"
                            type="button"
                            class="link link-primary"
                            @click="navigate(run.tenant_id, action.url)"
                        >
                            {{ action.label }}
                        </button>
                        <template v-else>{{ action.label }}</template>
                    </li>
                </ul>
            </div>
        </template>

        <div v-if="thread.pending_actions.length > 0" class="text-sm">
            <Link href="/pending-actions" class="link link-primary">
                {{ t('task_ai_pending_link', { count: thread.pending_actions.length }) }}
            </Link>
            <span v-if="pendingExpiry" class="text-muted">
                · {{ t('task_ai_pending_expires', { date: formatDatetime(pendingExpiry) }) }}
            </span>
        </div>

        <div v-if="alertMessage" role="alert" class="alert alert-error alert-soft text-sm">{{ alertMessage }}</div>
        <p v-if="statusCheckFailed" class="text-xs text-warning">{{ t('task_ai_status_check_failed') }}</p>

        <SelectInput
            v-if="needsCompany && kind"
            :model-value="companyId ?? ''"
            :label="t('task_ai_company')"
            :options="companyOptions"
            :error="errors.company_id"
            @update:model-value="pickedCompanyId = $event === '' ? null : $event"
        />

        <p v-if="!thread.ai_available" class="text-xs text-muted">{{ t('task_ai_not_configured') }}</p>
        <template v-else-if="canStart">
            <p v-if="continueUnanswered" class="text-xs text-muted">{{ t('task_ai_continue_without_answer') }}</p>
            <p class="text-xs text-muted">{{ t('task_ai_hint_short') }}</p>
        </template>
    </TaskPanelSection>
</template>
