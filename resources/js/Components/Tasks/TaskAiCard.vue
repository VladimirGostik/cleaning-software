<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SelectInput from '@/Components/Forms/SelectInput.vue';
import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
import TaskAiRunBadge from './TaskAiRunBadge.vue';
import { useTenantSwitchVisit } from '@/Composables/useTenantSwitchVisit';
import { formatDatetime } from '@/utils/date';
import {
    defaultRunCompanyId,
    earliestPendingExpiry,
    isTaskRunContinuePrimary,
    taskRunActionKind,
    taskRunStatusTextKey,
} from '@/utils/taskAi';

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

const NOT_CONFIGURED_ID = 'task-ai-not-configured';
const ACTION_LABEL = { start: 'task_ai_start', continue: 'task_ai_continue', start_again: 'task_ai_start_again' };

const run = computed(() => props.thread.latest_run);
const kind = computed(() => taskRunActionKind(props.thread));
const continuePrimary = computed(() => kind.value === 'continue' && isTaskRunContinuePrimary(props.thread));
const needsCompany = computed(() => props.thread.run_companies.length > 0);
const showQuestionLink = computed(() => run.value?.status === 'needs_input' && props.thread.question !== null);
const notConfigured = computed(() => kind.value !== null && !props.thread.ai_available);
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
    () =>
        !props.starting &&
        !props.uploading &&
        props.thread.ai_available &&
        (!needsCompany.value || companyId.value !== null),
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
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-3">
            <div class="flex items-center gap-2">
                <h2 class="card-title flex-1 text-base">{{ t('task_ai_title') }}</h2>
                <TaskAiRunBadge v-if="run" :status="run.status" />
            </div>

            <template v-if="run">
                <p
                    class="flex items-center gap-2"
                    :role="run.is_active ? 'status' : run.status === 'failed' ? 'alert' : undefined"
                >
                    <span v-if="run.is_active" class="loading loading-spinner loading-sm" aria-hidden="true" />
                    {{ t(taskRunStatusTextKey(run)) }}
                </p>
                <p class="text-sm text-base-content/60">
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
                <span v-if="pendingExpiry" class="text-base-content/60">
                    · {{ t('task_ai_pending_expires', { date: formatDatetime(pendingExpiry) }) }}
                </span>
            </div>

            <div v-if="alertMessage" role="alert" class="alert alert-error alert-soft text-sm">{{ alertMessage }}</div>
            <p v-if="statusCheckFailed" class="text-sm text-warning">{{ t('task_ai_status_check_failed') }}</p>

            <SelectInput
                v-if="needsCompany && kind"
                :model-value="companyId ?? ''"
                :label="t('task_ai_company')"
                :options="companyOptions"
                :error="errors.company_id"
                @update:model-value="pickedCompanyId = $event === '' ? null : $event"
            />

            <div v-if="kind || showQuestionLink" class="flex flex-wrap gap-2">
                <button v-if="showQuestionLink" type="button" class="btn btn-neutral btn-sm" @click="scrollToQuestion">
                    {{ t('task_ai_go_to_question') }}
                </button>
                <button
                    v-if="kind"
                    type="button"
                    class="btn btn-sm"
                    :class="kind === 'continue' && !continuePrimary ? 'btn-neutral' : 'btn-primary'"
                    :disabled="!canPress"
                    :aria-describedby="notConfigured ? NOT_CONFIGURED_ID : undefined"
                    @click="press"
                >
                    <span v-if="starting" class="loading loading-spinner loading-xs" aria-hidden="true" />
                    {{ t(ACTION_LABEL[kind]) }}
                </button>
            </div>

            <p v-if="notConfigured" :id="NOT_CONFIGURED_ID" class="text-sm text-base-content/60">
                {{ t('task_ai_not_configured') }}
            </p>
            <p v-else-if="kind === 'continue' && !continuePrimary" class="text-sm text-base-content/60">
                {{ t('task_ai_continue_without_answer') }}
            </p>
            <p v-if="kind" class="text-sm text-base-content/60">{{ t('task_ai_hint') }}</p>
        </div>
    </div>
</template>
