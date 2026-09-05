<script setup lang="ts">
    import { computed, ref } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormProvider from '@/Components/Forms/FormProvider.vue';
    import FormActions from '@/Components/Forms/FormActions.vue';
    import TextInput from '@/Components/Forms/TextInput.vue';
    import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
    import ToggleInput from '@/Components/Forms/ToggleInput.vue';
    import PermissionCheckboxGroups, {
        type PermissionGroup,
    } from '@/Components/Employees/PermissionCheckboxGroups.vue';
    import EmploymentContractFields, {
        type ContractFormEmploymentData,
    } from '@/Components/Contracts/EmploymentContractFields.vue';
    import { useTranslate } from '@/Composables/useTranslate';

    interface EmployeeFormData {
        email: string;
        first_name: string | null;
        last_name: string | null;
        phone: string | null;
        role_name: string;
        permissions: string[];
        employment: ContractFormEmploymentData | null;
    }

    const props = defineProps<{
        mode: 'create' | 'edit';
        employee?: App.Data.Employees.EmployeeDetailData | null;
        roleOptions: SelectOption[];
        permissionGroups: PermissionGroup[];
        employmentTypeOptions: SelectOption[];
    }>();

    const { t } = useTranslate();

    const isEdit = props.mode === 'edit' && !!props.employee;

    function mapEmployment(
        ec: App.Data.Contracts.EmploymentContractData | null | undefined,
    ): ContractFormEmploymentData | null {
        if (!ec) return null;
        return {
            employment_type: ec.employment_type,
            position: ec.position,
            hourly_rate: ec.hourly_rate !== null ? Number(ec.hourly_rate) || null : null,
            monthly_salary: ec.monthly_salary !== null ? Number(ec.monthly_salary) || null : null,
            weekly_hours: ec.weekly_hours !== null ? Number(ec.weekly_hours) || null : null,
            probation_end_date: ec.probation_end_date,
        };
    }

    const defaultEmployment: ContractFormEmploymentData = {
        employment_type: 'dpp',
        position: null,
        hourly_rate: null,
        monthly_salary: null,
        weekly_hours: null,
        probation_end_date: null,
    };

    // eslint-disable-next-line no-restricted-syntax -- imperative toggle UI state
    const hasEmployment = ref<boolean>(!!props.employee?.employment_contract);

    const form = useForm<EmployeeFormData>(
        isEdit ? 'put' : 'post',
        isEdit ? `/employees/${props.employee!.id}` : '/employees',
        {
            email: props.employee?.user_email ?? '',
            first_name: props.employee?.first_name ?? null,
            last_name: props.employee?.last_name ?? null,
            phone: props.employee?.phone ?? null,
            role_name: props.employee?.role_name ?? '',
            permissions: [...(props.employee?.permissions ?? [])],
            employment: mapEmployment(props.employee?.employment_contract),
        },
    );

    function onToggleEmployment(value: boolean): void {
        hasEmployment.value = value;
        form.employment = value ? { ...defaultEmployment } : null;
    }

    function onUpdateEmployment(data: ContractFormEmploymentData): void {
        form.employment = data;
    }

    const permissionsError = computed<string | undefined>(() => form.errors.permissions ?? undefined);

    function submit(): void {
        form.submit();
    }
</script>

<template>
    <FormProvider :form="form">
        <form novalidate @submit.prevent="submit">
            <!-- Profile section -->
            <div class="card bg-base-100 shadow-sm mb-6">
                <div class="card-body">
                    <h2 class="card-title text-base mb-4">{{ t('employees.section.profile') }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <TextInput
                                field="email"
                                type="email"
                                :label="t('employees.form.email')"
                                :disabled="isEdit"
                                required
                            />
                        </div>
                        <TextInput field="first_name" :label="t('employees.form.first_name')" />
                        <TextInput field="last_name" :label="t('employees.form.last_name')" />
                        <TextInput field="phone" type="tel" :label="t('employees.form.phone')" />
                    </div>
                </div>
            </div>

            <!-- Role & permissions section -->
            <div class="card bg-base-100 shadow-sm mb-6">
                <div class="card-body">
                    <h2 class="card-title text-base mb-4">{{ t('employees.section.role') }}</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <SelectInput
                                field="role_name"
                                :label="t('employees.form.role')"
                                :options="roleOptions"
                                :placeholder="t('employees.filter.all_roles')"
                                required
                            />
                        </div>
                    </div>
                    <div class="mt-4">
                        <PermissionCheckboxGroups
                            v-model="form.permissions"
                            :groups="permissionGroups"
                            :error="permissionsError"
                        />
                    </div>
                </div>
            </div>

            <!-- Employment contract section -->
            <div class="card bg-base-100 shadow-sm mb-6">
                <div class="card-body">
                    <h2 class="card-title text-base mb-4">
                        {{ t('employees.section.contract') }}
                    </h2>
                    <ToggleInput
                        :model-value="hasEmployment"
                        :label="t('employees.form.employment')"
                        @update:model-value="onToggleEmployment"
                    />
                    <div v-if="hasEmployment && form.employment" class="mt-4">
                        <EmploymentContractFields
                            :employment="form.employment"
                            :errors="form.errors"
                            :employment-type-options="employmentTypeOptions"
                            @update:employment="onUpdateEmployment"
                        />
                    </div>
                </div>
            </div>

            <FormActions
                cancel-href="/employees"
                :cancel-label="t('employees.form.cancel')"
                :submit-label="t('employees.form.save')"
                :processing="form.processing"
            />
        </form>
    </FormProvider>
</template>
