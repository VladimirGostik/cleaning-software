<script setup lang="ts">
    import { computed } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { PlusIcon, UserGroupIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
    import AppLayout from '@/Layouts/AppLayout.vue';

    defineOptions({ layout: AppLayout });

    import PageHeader from '@/Components/PageHeader.vue';
    import EmptyState from '@/Components/EmptyState.vue';
    import Can from '@/Components/Can.vue';
    import Pagination from '@/Components/Pagination.vue';
    import EmployeeStatusBadge from '@/Components/Employees/EmployeeStatusBadge.vue';
    import EmployeeFiltersBar from '@/Components/Employees/EmployeeFiltersBar.vue';
    import { useTranslate } from '@/Composables/useTranslate';
    import { usePageProps } from '@/Composables/usePageProps';
    import { useEmployeeFilters } from '@/Composables/useEmployeeFilters';
    import type { SelectOption } from '@/Components/Forms/SelectInput.vue';
    import type { PaginatedData } from '@/types/pagination';

    interface Props {
        employees: PaginatedData<App.Data.Employees.EmployeeListItemData>;
        filters: {
            search?: string | null;
            role?: string | null;
            is_active?: boolean | null;
            per_page?: number;
        };
        roleOptions: SelectOption[];
    }

    const props = defineProps<Props>();

    const { t } = useTranslate();
    const pageProps = usePageProps();
    const flash = computed(() => pageProps.flash);

    const { state: filterState } = useEmployeeFilters(props.filters);

    const meta = computed(() => props.employees.meta);
    const links = computed(() => props.employees.links);

    function goToDetail(id: string): void {
        router.get(`/employees/${id}`);
    }
</script>

<template>
    <div class="page-container">
        <div v-if="flash.success" class="alert alert-success mb-4">
            <span>{{ flash.success }}</span>
        </div>

        <PageHeader :title="t('employees.title')">
            <template #actions>
                <Can permission="create employees" feature="employees">
                    <a href="/employees/create" class="btn btn-primary">
                        <PlusIcon class="w-4 h-4" />
                        {{ t('employees.add') }}
                    </a>
                </Can>
            </template>
        </PageHeader>

        <EmployeeFiltersBar
            v-model:search="filterState.search"
            v-model:role="filterState.role"
            v-model:is-active="filterState.is_active"
            :role-options="roleOptions"
        />

        <!-- Desktop table -->
        <div class="card bg-base-100 shadow-sm hidden md:block">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>{{ t('employees.col.name') }}</th>
                            <th>{{ t('employees.col.email') }}</th>
                            <th>{{ t('employees.col.phone') }}</th>
                            <th>{{ t('employees.col.role') }}</th>
                            <th>{{ t('employees.col.objects') }}</th>
                            <th>{{ t('employees.col.employment') }}</th>
                            <th>{{ t('employees.col.status') }}</th>
                            <th>{{ t('employees.col.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in employees.data"
                            :key="row.id"
                            class="hover cursor-pointer"
                            role="button"
                            tabindex="0"
                            @click="goToDetail(row.id)"
                            @keydown.enter="goToDetail(row.id)"
                        >
                            <td class="font-medium">{{ row.display_name }}</td>
                            <td class="text-base-content/70 text-sm">{{ row.email }}</td>
                            <td class="text-base-content/70 text-sm">
                                {{ row.phone ?? t('common.empty_dash') }}
                            </td>
                            <td class="text-sm">
                                {{
                                    row.role_name
                                        ? t('employee_role.' + row.role_name)
                                        : t('common.empty_dash')
                                }}
                            </td>
                            <td class="text-sm">{{ row.assigned_objects_count }}</td>
                            <td class="text-sm">
                                {{
                                    row.employment_type
                                        ? t('employment_type.' + row.employment_type)
                                        : t('common.empty_dash')
                                }}
                            </td>
                            <td>
                                <EmployeeStatusBadge :active="row.is_active" />
                            </td>
                            <td @click.stop>
                                <Can permission="edit employees">
                                    <a
                                        :href="`/employees/${row.id}/edit`"
                                        class="btn btn-ghost btn-xs"
                                    >
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </a>
                                </Can>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden flex flex-col gap-2">
            <div
                v-for="row in employees.data"
                :key="row.id"
                class="card bg-base-100 shadow-sm p-4 cursor-pointer"
                role="button"
                tabindex="0"
                @click="goToDetail(row.id)"
                @keydown.enter="goToDetail(row.id)"
            >
                <div class="flex justify-between items-start gap-2">
                    <div>
                        <p class="font-medium">{{ row.display_name }}</p>
                        <p class="text-sm text-base-content/60 mt-0.5">{{ row.email }}</p>
                    </div>
                    <EmployeeStatusBadge :active="row.is_active" />
                </div>
                <div class="flex gap-3 text-sm mt-2 text-base-content/70">
                    <span v-if="row.role_name">{{ t('employee_role.' + row.role_name) }}</span>
                    <span v-if="row.phone">{{ row.phone }}</span>
                </div>
            </div>
        </div>

        <EmptyState
            v-if="employees.data.length === 0"
            :title="t('employees.empty')"
            :description="t('employees.empty_hint')"
            :icon="UserGroupIcon"
        >
            <template #cta>
                <Can permission="create employees" feature="employees">
                    <a href="/employees/create" class="btn btn-primary btn-sm">
                        {{ t('employees.add') }}
                    </a>
                </Can>
            </template>
        </EmptyState>

        <Pagination v-if="meta.last_page > 1" :meta="meta" :links="links" />
    </div>
</template>
