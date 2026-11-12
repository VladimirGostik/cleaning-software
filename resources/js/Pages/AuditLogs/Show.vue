<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import StatusErrorAlert from '@/Components/StatusErrorAlert.vue';
import ActivityChangesDiff from '@/Components/AuditLogs/ActivityChangesDiff.vue';
import ActivityRevertCard from '@/Components/AuditLogs/ActivityRevertCard.vue';
import ActivityRevertStatus from '@/Components/AuditLogs/ActivityRevertStatus.vue';
import { useActivityRevertConfirm } from '@/Composables/useActivityRevertConfirm';
import { revertSubjectLabel } from '@/utils/auditLog';
import { formatDatetime } from '@/utils/date';
import type { Breadcrumb } from '@/types';

const { t } = useI18n();
const revertConfirm = useActivityRevertConfirm();

const props = defineProps<{
    activity: App.Data.ActivityLogDetailData;
}>();

const breadcrumbs: Breadcrumb[] = [
    { label: t('dashboard'), url: '/' },
    { label: t('audit_logs'), url: '/audit-logs' },
    { label: props.activity.subject_label ?? props.activity.description },
];

function formatJson(data: Record<string, unknown> | null): string {
    if (!data) return '';
    return JSON.stringify(data, null, 2);
}
</script>

<template>
    <Header :title="t('audit_log_detail')" :breadcrumbs="breadcrumbs">
        <template #actions>
            <Link href="/audit-logs" class="btn btn-ghost">
                {{ t('back') }}
            </Link>
        </template>
    </Header>

    <StatusErrorAlert class="mb-4" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card bg-base-100">
            <div class="card-body">
                <h2 class="card-title">
                    {{ t('basic_info') }}
                </h2>
                <dl class="kg-list">
                    <div class="kg-list-row justify-between">
                        <dt class="text-sm text-muted">{{ t('description') }}</dt>
                        <dd class="text-sm font-medium">{{ activity.description }}</dd>
                    </div>
                    <div class="kg-list-row justify-between">
                        <dt class="text-sm text-muted">{{ t('event') }}</dt>
                        <dd>
                            <span v-if="activity.event" class="kg-chip kg-chip--neutral kg-chip--sm">
                                {{ activity.event }}
                            </span>
                            <span v-else>-</span>
                        </dd>
                    </div>
                    <div class="kg-list-row justify-between">
                        <dt class="text-sm text-muted">{{ t('audit_subject') }}</dt>
                        <dd class="text-sm font-medium">{{ revertSubjectLabel(activity) }}</dd>
                    </div>
                    <div class="kg-list-row justify-between">
                        <dt class="text-sm text-muted">{{ t('audit_revert_column') }}</dt>
                        <dd><ActivityRevertStatus :revert="activity.revert" /></dd>
                    </div>
                    <div class="kg-list-row justify-between">
                        <dt class="text-sm text-muted">{{ t('subject_type') }}</dt>
                        <dd class="text-sm">{{ activity.subject_type ?? '-' }}</dd>
                    </div>
                    <div class="kg-list-row justify-between">
                        <dt class="text-sm text-muted">{{ t('subject_id') }}</dt>
                        <dd class="text-sm">{{ activity.subject_id ?? '-' }}</dd>
                    </div>
                    <div class="kg-list-row justify-between">
                        <dt class="text-sm text-muted">{{ t('created_at') }}</dt>
                        <dd class="text-sm">{{ formatDatetime(activity.created_at) }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="card bg-base-100">
            <div class="card-body">
                <h2 class="card-title">{{ t('causer') }}</h2>
                <dl class="kg-list">
                    <div class="kg-list-row justify-between">
                        <dt class="text-sm text-muted">{{ t('name') }}</dt>
                        <dd class="text-sm font-medium">{{ activity.causer_name ?? '-' }}</dd>
                    </div>
                    <div class="kg-list-row justify-between">
                        <dt class="text-sm text-muted">{{ t('email') }}</dt>
                        <dd class="text-sm">{{ activity.causer_email ?? '-' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <ActivityRevertCard
            :revert="activity.revert"
            :changes="activity.changes"
            @revert="revertConfirm.openModal(activity)"
        />

        <div class="card bg-base-100 lg:col-span-2">
            <div class="card-body">
                <h2 class="card-title">{{ t('attribute_changes') }}</h2>
                <ActivityChangesDiff :changes="activity.changes" :event="activity.event" />
            </div>
        </div>

        <details v-if="activity.attribute_changes || activity.properties" class="card bg-base-100 lg:col-span-2">
            <summary class="card-body cursor-pointer text-lg font-medium">{{ t('audit_technical_data') }}</summary>
            <div class="card-body pt-0">
                <template v-if="activity.attribute_changes">
                    <h3 class="text-sm font-medium">{{ t('attribute_changes') }}</h3>
                    <pre class="bg-base-200 rounded-card-nested p-4 text-sm overflow-x-auto">{{
                        formatJson(activity.attribute_changes)
                    }}</pre>
                </template>
                <template v-if="activity.properties">
                    <h3 class="text-sm font-medium">{{ t('properties') }}</h3>
                    <pre class="bg-base-200 rounded-card-nested p-4 text-sm overflow-x-auto">{{
                        formatJson(activity.properties)
                    }}</pre>
                </template>
            </div>
        </details>
    </div>

    <ConfirmDeleteModal
        :is-open="revertConfirm.state.isOpen"
        :title="revertConfirm.getModalTitle()"
        :description="revertConfirm.getModalDescription()"
        :confirm-label="t('audit_revert_action')"
        confirm-variant="warning"
        @cancel="revertConfirm.closeModal"
        @confirm="revertConfirm.confirmDelete"
    />
</template>
