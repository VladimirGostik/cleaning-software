<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ExclamationTriangleIcon from '~icons/material-symbols/warning-outline-rounded';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ObjectStatusBadge from '@/Components/Objects/ObjectStatusBadge.vue';
import EmployeeProfileCard from '@/Components/Employees/EmployeeProfileCard.vue';
import EmployeeAccessCard from '@/Components/Employees/EmployeeAccessCard.vue';
import EmployeeEmploymentCard from '@/Components/Employees/EmployeeEmploymentCard.vue';
import EmployeeAccessModal from '@/Components/Employees/EmployeeAccessModal.vue';
import EmployeeActionsCard from '@/Components/Employees/EmployeeActionsCard.vue';
import EmployeeMetaCard from '@/Components/Employees/EmployeeMetaCard.vue';
import EmployeePersonalCard from '@/Components/Employees/EmployeePersonalCard.vue';
import AttachedFilesCard from '@/Components/Documents/AttachedFilesCard.vue';
import EmployeeExpensesCard from '@/Components/Employees/EmployeeExpensesCard.vue';
import RelatedContractsCard from '@/Components/Contracts/RelatedContractsCard.vue';
import EmployeeRoleModal from '@/Components/Employees/EmployeeRoleModal.vue';

import { employeeTypeKey, employmentTypeForEmployeeType } from '@/utils/enums';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    employee: App.Data.Employees.EmployeeDetailData;
    roleOptions: App.Data.RoleListItemData[];
    personal: App.Data.Employees.EmployeePersonalData | null;
    documents: App.Data.Employees.EmployeeDocumentData[] | null;
    documentConstraints: App.Data.Employees.EmployeeDocumentConstraintsData | null;
    contracts: App.Data.Contracts.ContractListItemData[] | null;
    expenses: App.Data.Expenses.ExpenseListItemData[] | null;
    canCreateContract: boolean;
}>();

const { t } = useI18n();

const breadcrumbs = computed<Breadcrumb[]>(() => [
    { label: t('dashboard'), url: '/' },
    { label: t('employees'), url: '/employees' },
    { label: props.employee.display_name },
]);

const canUpdate = computed(() => props.employee.can.update);
const canDeactivate = computed(() => props.employee.can.deactivate);
const canReactivate = computed(() => props.employee.can.reactivate);
const canAssignRole = computed(() => props.employee.can.assign_role);

const canGrantAccess = computed(() => props.employee.can.grant_access === true);
const canRevokeAccess = computed(() => props.employee.can.revoke_access === true);
const canResendInvitation = computed(() => props.employee.can.resend_invitation === true);

const ui = reactive({ roleOpen: false, accessOpen: false });
const reactivating = ref(false);
const resending = ref(false);

const createContractHref = computed(() => {
    const base = `/contracts/create?contractable_type=tenant_membership&category=employment&contractable_id=${props.employee.id}`;
    return props.employee.employee_type
        ? `${base}&employment_type=${employmentTypeForEmployeeType(props.employee.employee_type)}`
        : base;
});

function resend(): void {
    router.post(`/employees/${props.employee.id}/access/invitation`, undefined, {
        preserveScroll: true,
        onStart: () => {
            resending.value = true;
        },
        onFinish: () => {
            resending.value = false;
        },
    });
}

function reactivate(): void {
    router.post(`/employees/${props.employee.id}/reactivate`, undefined, {
        preserveScroll: true,
        onStart: () => {
            reactivating.value = true;
        },
        onFinish: () => {
            reactivating.value = false;
        },
    });
}

const revokeConfirm = useDeleteConfirm<App.Data.Employees.EmployeeDetailData>({
    method: 'delete',
    resolveUrl: (e) => `/employees/${e.id}/access`,
    getTitle: () => t('employee_access_revoke'),
    getDescription: (e) => t('employee_access_revoke_confirm', { name: e.display_name }),
});

const deactivateConfirm = useDeleteConfirm<App.Data.Employees.EmployeeDetailData>({
    method: 'post',
    resolveUrl: (e) => `/employees/${e.id}/deactivate`,
    getTitle: () => t('employee_deactivate'),
    getDescription: (e) =>
        [
            t('employee_deactivate_confirm', { name: e.display_name }),
            e.upcoming_jobs_count > 0 ? t('employee_deactivate_jobs_hint', { count: e.upcoming_jobs_count }) : '',
        ]
            .filter(Boolean)
            .join(' '),
});
</script>

<template>
    <Header :title="employee.display_name" :breadcrumbs="breadcrumbs">
        <template #actions>
            <ObjectStatusBadge :is-active="employee.is_active" />
            <span v-if="employee.employee_type" class="kg-chip kg-chip--neutral kg-chip--sm">
                {{ t(employeeTypeKey(employee.employee_type)) }}
            </span>
            <span v-if="employee.role_name" class="kg-chip kg-chip--neutral kg-chip--sm">{{ employee.role_name }}</span>
        </template>
    </Header>

    <div v-if="!employee.is_active" class="alert alert-warning mb-4">
        <ExclamationTriangleIcon />
        <span>{{ t('employee_inactive_banner') }}</span>
        <button
            v-if="canReactivate"
            type="button"
            class="btn btn-sm btn-primary"
            :disabled="reactivating"
            @click="reactivate"
        >
            {{ t('employee_reactivate') }}
        </button>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
        <div class="space-y-4">
            <EmployeeProfileCard :employee="employee" />
            <EmployeeEmploymentCard :employee="employee" />
            <EmployeeAccessCard :employee="employee" />
            <EmployeePersonalCard v-if="personal" :personal="personal" />
            <AttachedFilesCard
                v-if="documents !== null"
                :heading="t('employee_section_documents')"
                :documents="documents"
                :upload-url="`/employees/${employee.id}/documents`"
                :delete-url="(id) => `/employees/${employee.id}/documents/${id}`"
                :constraints="documentConstraints"
                :can-manage="employee.can.manage_documents === true"
                :title-max-length="255"
            />
            <EmployeeExpensesCard v-if="expenses !== null" :employee-id="employee.id" :expenses="expenses" />
            <RelatedContractsCard
                :contracts="contracts"
                :can-create="canCreateContract"
                :create-href="createContractHref"
            />
        </div>

        <div class="space-y-4">
            <EmployeeActionsCard
                :employee="employee"
                :can-update="canUpdate"
                :can-deactivate="canDeactivate"
                :can-reactivate="canReactivate"
                :can-assign-role="canAssignRole"
                :can-grant-access="canGrantAccess"
                :can-revoke-access="canRevokeAccess"
                :can-resend-invitation="canResendInvitation"
                :resending="resending"
                @deactivate="deactivateConfirm.openModal(employee)"
                @reactivate="reactivate"
                @change-role="ui.roleOpen = true"
                @grant-access="ui.accessOpen = true"
                @revoke-access="revokeConfirm.openModal(employee)"
                @resend-invitation="resend"
            />
            <EmployeeMetaCard :employee="employee" />
        </div>
    </div>

    <ConfirmDeleteModal
        :is-open="deactivateConfirm.state.isOpen"
        :title="deactivateConfirm.getModalTitle()"
        :description="deactivateConfirm.getModalDescription()"
        confirm-variant="warning"
        :confirm-label="t('employee_deactivate')"
        @cancel="deactivateConfirm.closeModal"
        @confirm="deactivateConfirm.confirmDelete"
    />

    <ConfirmDeleteModal
        :is-open="revokeConfirm.state.isOpen"
        :title="revokeConfirm.getModalTitle()"
        :description="revokeConfirm.getModalDescription()"
        confirm-variant="warning"
        :confirm-label="t('employee_access_revoke')"
        @cancel="revokeConfirm.closeModal"
        @confirm="revokeConfirm.confirmDelete"
    />

    <EmployeeAccessModal
        v-if="ui.accessOpen"
        :employee-id="employee.id"
        :contact-email="employee.contact_email"
        :roles="roleOptions"
        @close="ui.accessOpen = false"
    />

    <EmployeeRoleModal
        :open="ui.roleOpen"
        :employee-id="employee.id"
        :current-role="employee.role_name"
        :roles="roleOptions"
        @close="ui.roleOpen = false"
    />
</template>
