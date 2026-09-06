<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

import AppLayout from '@/Layouts/AppLayout.vue';
import Header from '@/Layouts/Header.vue';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import ObjectStatusBadge from '@/Components/Objects/ObjectStatusBadge.vue';
import EmployeeProfileCard from '@/Components/Employees/EmployeeProfileCard.vue';
import EmployeeRoleCard from '@/Components/Employees/EmployeeRoleCard.vue';
import EmployeeActionsCard from '@/Components/Employees/EmployeeActionsCard.vue';
import EmployeeMetaCard from '@/Components/Employees/EmployeeMetaCard.vue';
import EmployeeRoleModal from '@/Components/Employees/EmployeeRoleModal.vue';

import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import type { Breadcrumb } from '@/types';

const props = defineProps<{
    employee: App.Data.Employees.EmployeeDetailData;
    roleOptions: App.Data.RoleListItemData[];
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

const ui = reactive({ roleOpen: false });
const reactivating = ref(false);

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
    <AppLayout>
        <Header :title="employee.display_name" :breadcrumbs="breadcrumbs">
            <template #actions>
                <ObjectStatusBadge :is-active="employee.is_active" />
                <span v-if="employee.role_name" class="badge badge-primary badge-sm">{{ employee.role_name }}</span>
            </template>
        </Header>

        <div v-if="!employee.is_active" class="alert alert-warning mb-6">
            <ExclamationTriangleIcon class="size-5" />
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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_280px]">
            <div class="space-y-6">
                <EmployeeProfileCard :employee="employee" />
                <EmployeeRoleCard :employee="employee" />
            </div>

            <div class="space-y-6">
                <EmployeeActionsCard
                    :employee="employee"
                    :can-update="canUpdate"
                    :can-deactivate="canDeactivate"
                    :can-reactivate="canReactivate"
                    :can-assign-role="canAssignRole"
                    @deactivate="deactivateConfirm.openModal(employee)"
                    @reactivate="reactivate"
                    @change-role="ui.roleOpen = true"
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

        <EmployeeRoleModal
            :open="ui.roleOpen"
            :employee-id="employee.id"
            :current-role="employee.role_name"
            :roles="roleOptions"
            @close="ui.roleOpen = false"
        />
    </AppLayout>
</template>
