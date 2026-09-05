<script setup lang="ts">
    import { computed } from 'vue';
    import { Link, router } from '@inertiajs/vue3';
    import { PencilSquareIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import Can from '@/Components/Can.vue';
    import ConfirmDialog from '@/Components/ConfirmDialog.vue';
    import EmployeeStatusBadge from '@/Components/Employees/EmployeeStatusBadge.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useLocalizedDate } from '@/Composables/useLocalizedDate';

    import { ref } from 'vue';

    interface Props {
        employee: App.Data.Employees.EmployeeDetailData;
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);
    const { formatDate } = useLocalizedDate();

    // eslint-disable-next-line no-restricted-syntax -- imperative modal toggle
    const deactivateConfirmOpen = ref(false);

    function deactivate(): void {
        router.post(
            `/employees/${props.employee.id}/deactivate`,
            {},
            {
                onSuccess: () => {
                    deactivateConfirmOpen.value = false;
                },
            },
        );
    }
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <div class="breadcrumbs text-sm mb-4">
            <ul>
                <li>
                    <Link href="/employees">{{ t('employees.title') }}</Link>
                </li>
                <li>{{ employee.display_name }}</li>
            </ul>
        </div>

        <PageHeader :title="employee.display_name">
            <template #badges>
                <EmployeeStatusBadge :active="employee.is_active" />
                <span v-if="employee.role_name" class="badge badge-ghost">
                    {{ t('employee_role.' + employee.role_name) }}
                </span>
            </template>
            <template #actions>
                <Can permission="edit employees">
                    <a :href="`/employees/${employee.id}/edit`" class="btn btn-ghost btn-sm">
                        <PencilSquareIcon class="w-4 h-4" />
                        {{ t('employees.action.edit') }}
                    </a>
                </Can>
                <Can permission="delete employees">
                    <button
                        v-if="employee.is_active"
                        type="button"
                        class="btn btn-ghost btn-sm text-error"
                        @click="deactivateConfirmOpen = true"
                    >
                        {{ t('employees.action.deactivate') }}
                    </button>
                </Can>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6">
            <!-- Left column -->
            <div class="flex flex-col gap-4">
                <!-- Profile -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base mb-3">
                            {{ t('employees.detail.profile') }}
                        </h2>
                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-base-content/60">{{ t('employees.form.email') }}</dt>
                                <dd class="font-medium mt-0.5">{{ employee.user_email }}</dd>
                            </div>
                            <div v-if="employee.phone">
                                <dt class="text-base-content/60">{{ t('employees.form.phone') }}</dt>
                                <dd class="font-medium mt-0.5">{{ employee.phone }}</dd>
                            </div>
                            <div v-if="employee.position">
                                <dt class="text-base-content/60">{{ t('employees.form.position') }}</dt>
                                <dd class="font-medium mt-0.5">{{ employee.position }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Role & permissions -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base mb-3">
                            {{ t('employees.detail.permissions') }}
                        </h2>
                        <p v-if="employee.role_name" class="text-sm font-medium mb-3">
                            {{ t('employee_role.' + employee.role_name) }}
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="perm in employee.permissions"
                                :key="perm"
                                class="badge badge-outline badge-sm"
                            >
                                {{ perm }}
                            </span>
                            <span
                                v-if="employee.permissions.length === 0"
                                class="text-sm text-base-content/50"
                            >
                                {{ t('common.empty_dash') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Employment contract -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base mb-3">
                            {{ t('employees.section.contract') }}
                        </h2>

                        <div v-if="employee.employment_contract">
                            <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt class="text-base-content/60">
                                        {{ t('contracts.form.employment_type') }}
                                    </dt>
                                    <dd class="font-medium mt-0.5">
                                        {{
                                            t(
                                                'employment_type.' +
                                                    employee.employment_contract.employment_type,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div v-if="employee.employment_contract.position">
                                    <dt class="text-base-content/60">
                                        {{ t('contracts.form.employment_position') }}
                                    </dt>
                                    <dd class="font-medium mt-0.5">
                                        {{ employee.employment_contract.position }}
                                    </dd>
                                </div>
                                <div v-if="employee.employment_contract.hourly_rate">
                                    <dt class="text-base-content/60">
                                        {{ t('contracts.form.employment_hourly_rate') }}
                                    </dt>
                                    <dd class="font-medium mt-0.5">
                                        {{ employee.employment_contract.hourly_rate }}
                                    </dd>
                                </div>
                                <div v-if="employee.employment_contract.monthly_salary">
                                    <dt class="text-base-content/60">
                                        {{ t('contracts.form.employment_monthly_salary') }}
                                    </dt>
                                    <dd class="font-medium mt-0.5">
                                        {{ employee.employment_contract.monthly_salary }}
                                    </dd>
                                </div>
                                <div v-if="employee.employment_contract.probation_end_date">
                                    <dt class="text-base-content/60">
                                        {{ t('contracts.form.employment_probation_end_date') }}
                                    </dt>
                                    <dd class="font-medium mt-0.5">
                                        {{ formatDate(employee.employment_contract.probation_end_date) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <p v-else class="text-sm text-base-content/50">
                            {{ t('employees.detail.no_employment') }}
                        </p>
                    </div>
                </div>

                <!-- Assigned objects -->
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-base mb-3">
                            {{ t('employees.detail.assigned_objects') }}
                        </h2>

                        <div v-if="employee.assigned_objects.length > 0" class="flex flex-col gap-2">
                            <div
                                v-for="obj in employee.assigned_objects"
                                :key="(obj as { id: string }).id"
                                class="text-sm"
                            >
                                {{ (obj as { name: string }).name }}
                            </div>
                        </div>
                        <p v-else class="text-sm text-base-content/50">
                            {{ t('employees.detail.no_objects') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right sidebar -->
            <div class="flex flex-col gap-4">
                <div class="card bg-base-100 shadow-sm">
                    <div class="card-body p-4 text-sm flex flex-col gap-3">
                        <div>
                            <p class="text-base-content/60">{{ t('employees.detail.joined') }}</p>
                            <p class="font-medium mt-0.5">{{ formatDate(employee.joined_at) }}</p>
                        </div>
                        <div v-if="employee.other_tenants_count > 0">
                            <p class="text-base-content/60">
                                {{ t('employees.detail.other_tenants') }}
                            </p>
                            <p class="font-medium mt-0.5">{{ employee.other_tenants_count }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="deactivateConfirmOpen"
            :title="t('employees.action.deactivate')"
            :body="t('employees.deactivate_confirm').replace('{name}', employee.display_name)"
            :confirm-label="t('employees.action.deactivate')"
            :cancel-label="t('common.cancel')"
            confirm-variant="error"
            @confirm="deactivate"
            @cancel="deactivateConfirmOpen = false"
        />
    </div>
</template>
