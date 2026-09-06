<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import FormActions from '@/Components/Forms/FormActions.vue';

import EmployeeProfileFields from './EmployeeProfileFields.vue';
import EmployeeRoleFields from './EmployeeRoleFields.vue';
import EmployeeEmploymentSection from './EmployeeEmploymentSection.vue';
import type { EmploymentFormData } from '@/Components/Contracts/EmploymentContractFields.vue';

import { useAuthorization } from '@/Composables/useAuthorization';

interface EmployeeFormData {
    email: string;
    first_name: string | null;
    last_name: string | null;
    phone: string | null;
    position: string | null;
    role_name: string;
    permissions: string[];
    employment: EmploymentFormData | null;
}

const props = defineProps<{
    context: App.Data.Employees.EmployeeFormContextData;
    employee?: App.Data.Employees.EmployeeDetailData | null;
}>();

const { t } = useI18n();
const { allows } = useAuthorization();

const isEditing = computed(() => !!props.employee);

function initialData(): EmployeeFormData {
    return {
        email: props.employee?.email ?? '',
        first_name: props.employee?.first_name ?? null,
        last_name: props.employee?.last_name ?? null,
        phone: props.employee?.phone ?? null,
        position: props.employee?.position ?? null,
        role_name: props.employee?.role_name ?? '',
        permissions: [...(props.employee?.permissions ?? [])],
        employment: null,
    };
}

const form = useForm<EmployeeFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/employees/${props.employee!.id}` : '/employees',
    initialData(),
);

form.transform((data: EmployeeFormData) =>
    isEditing.value
        ? {
              first_name: data.first_name || null,
              last_name: data.last_name || null,
              phone: data.phone || null,
              position: data.position || null,
              role_name: data.role_name,
              permissions: data.permissions,
          }
        : {
              email: data.email,
              first_name: data.first_name || null,
              last_name: data.last_name || null,
              phone: data.phone || null,
              position: data.position || null,
              role_name: data.role_name,
              permissions: data.permissions,
              employment: data.employment,
          },
);

const grantableGroups = computed<App.Data.PermissionGroupData[]>(() =>
    props.context.permission_groups
        .map((g) => ({ ...g, permissions: g.permissions.filter((p) => allows(p.name)) }))
        .filter((g) => g.permissions.length > 0),
);

function updatePermissions(value: string[]): void {
    form.permissions = value;
    form.validate('permissions');
}

function submit(): void {
    form.submit();
}
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="space-y-6" @submit.prevent="submit">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body space-y-4">
                    <h2 class="card-title text-base">{{ t('employee_section_profile') }}</h2>
                    <EmployeeProfileFields :is-editing="isEditing" />
                </div>
            </div>

            <div class="card bg-base-100 shadow-sm">
                <div class="card-body space-y-4">
                    <h2 class="card-title text-base">{{ t('employee_section_role') }}</h2>
                    <EmployeeRoleFields
                        :roles="context.roles"
                        :permission-groups="grantableGroups"
                        :permissions="form.permissions"
                        :permissions-error="form.errors.permissions ?? null"
                        @update:permissions="updatePermissions"
                    />
                </div>
            </div>

            <div v-if="!isEditing" class="card bg-base-100 shadow-sm">
                <div class="card-body space-y-4">
                    <h2 class="card-title text-base">{{ t('employee_section_employment') }}</h2>
                    <EmployeeEmploymentSection
                        :employment="form.employment"
                        :errors="form.errors"
                        :disabled="form.processing"
                        @update:employment="form.employment = $event"
                    />
                </div>
            </div>

            <div v-else class="alert alert-info">
                <span>
                    {{ t('employee_employment_edit_hint') }}
                    <a href="/contracts" class="link link-hover">{{ t('contracts') }}</a>
                </span>
            </div>

            <FormActions
                :cancel-href="isEditing ? `/employees/${props.employee!.id}` : '/employees'"
                :submit-label="isEditing ? t('save') : t('employee_add')"
                :processing="form.processing"
            />
        </form>
    </FormProvider>
</template>
