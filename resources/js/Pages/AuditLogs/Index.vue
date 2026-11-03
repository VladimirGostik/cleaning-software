<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Header from '@/Layouts/Header.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import StatusErrorAlert from '@/Components/StatusErrorAlert.vue';
import ActivityRevertStatus from '@/Components/AuditLogs/ActivityRevertStatus.vue';
import { useActivityRevertConfirm } from '@/Composables/useActivityRevertConfirm';
import { useAuthorization } from '@/Composables/useAuthorization';
import { formatDatetime } from '@/utils/date';
import EyeIcon from '~icons/material-symbols/visibility-rounded';
import UndoIcon from '~icons/material-symbols/undo-rounded';
import type { Paginator, Breadcrumb, TableColumn } from '@/types';
import type { FilterConfig } from '@/types/table';

const { t } = useI18n();
const { allows } = useAuthorization();
const revertConfirm = useActivityRevertConfirm();

defineProps<{
    activities: Paginator<App.Data.ActivityLogListItemData>;
    query?: Record<string, unknown>;
}>();

const breadcrumbs: Breadcrumb[] = [{ label: t('dashboard'), url: '/' }, { label: t('audit_logs') }];

const columns: TableColumn[] = [
    { key: 'created_at', label: t('created_at'), sortable: true, class: 'whitespace-nowrap' },
    { key: 'description', label: t('description'), sortable: true },
    { key: 'subject_type', label: t('subject_type') },
    { key: 'event', label: t('event') },
    { key: 'causer_name', label: t('causer'), sortable: true },
    { key: 'revert', label: t('audit_revert_column') },
];

function canRevert(row: unknown): boolean {
    return (row as App.Data.ActivityLogListItemData).revert.status === 'available' && allows('revert audit logs');
}

const filterDefinitions = computed<FilterConfig[]>(() => [
    {
        property: 'subject_type',
        label: t('subject_type'),
        type: 'text',
        placeholder: t('subject_type'),
        defaultOperator: '~',
    },
    {
        property: 'created_at',
        label: t('created_at'),
        type: 'date',
        defaultOperator: '>=',
        operators: ['>=', '<=', 'between'],
    },
]);
</script>

<template>
    <Header :title="t('audit_logs')" :breadcrumbs="breadcrumbs" />

    <StatusErrorAlert class="mb-4" />

    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <DataTable
                :columns="columns"
                :rows="activities"
                :filters="filterDefinitions"
                route-name="audit-logs.index"
                :row-href="(row) => `/audit-logs/${(row as App.Data.ActivityLogListItemData).id}`"
            >
                <template #cell-created_at="{ value }">
                    {{ formatDatetime(value as string | null) }}
                </template>

                <template #cell-causer_name="{ row }">
                    <div>
                        <span class="font-medium">{{
                            (row as App.Data.ActivityLogListItemData).causer_name ?? '-'
                        }}</span>
                        <br />
                        <span class="text-xs text-base-content/60">
                            {{ (row as App.Data.ActivityLogListItemData).causer_email ?? '' }}
                        </span>
                    </div>
                </template>

                <template #cell-event="{ value }">
                    <span v-if="value" class="kg-chip kg-chip--neutral kg-chip--sm">
                        {{ value }}
                    </span>
                    <span v-else>-</span>
                </template>

                <template #cell-description="{ value }">
                    {{ value }}
                </template>

                <template #cell-revert="{ row }">
                    <ActivityRevertStatus :revert="(row as App.Data.ActivityLogListItemData).revert" />
                </template>

                <template #buttons="{ row }">
                    <button
                        v-if="canRevert(row)"
                        type="button"
                        class="btn btn-ghost btn-xs"
                        :title="t('audit_revert_action')"
                        :aria-label="t('audit_revert_action')"
                        @click.stop="revertConfirm.openModal(row as App.Data.ActivityLogListItemData)"
                    >
                        <UndoIcon class="size-5" />
                    </button>
                    <Link
                        :href="`/audit-logs/${(row as App.Data.ActivityLogListItemData).id}`"
                        class="btn btn-ghost btn-xs"
                        :title="t('details')"
                    >
                        <EyeIcon class="size-5" />
                    </Link>
                </template>
            </DataTable>
        </div>
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
