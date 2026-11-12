<script setup lang="ts">
import { computed, onBeforeUnmount } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import FormActions from '@/Components/Forms/FormActions.vue';

import EmployeeTypeFields from './EmployeeTypeFields.vue';
import EmployeeProfileFields from './EmployeeProfileFields.vue';
import EmployeeAccessSection from './EmployeeAccessSection.vue';
import EmployeeEmploymentSection from './EmployeeEmploymentSection.vue';
import EmployeePersonalFields from './EmployeePersonalFields.vue';
import { employeeStorePayload, employeeUpdatePayload, type EmployeeFormData } from './employeePayload';

import { cancelPendingValidation } from '@/Composables/cancelPendingValidation';
import { useAuthorization } from '@/Composables/useAuthorization';
import { useDependentValidation } from '@/Composables/useDependentValidation';
import { employmentTypeForEmployeeType } from '@/utils/enums';

const props = defineProps<{
    context: App.Data.Employees.EmployeeFormContextData;
    employee?: App.Data.Employees.EmployeeDetailData | null;
    personal?: App.Data.Employees.EmployeePersonalFormData | null;
}>();

const { t } = useI18n();
const { allows } = useAuthorization();

const isEditing = computed(() => !!props.employee);
const hasPersonalSection = computed(() => props.personal != null);

function initialData(): EmployeeFormData {
    return {
        has_app_access: false,
        employee_type: props.employee?.employee_type ?? null,
        email: props.employee?.email ?? '',
        contact_email: props.employee?.contact_email ?? null,
        first_name: props.employee?.first_name ?? null,
        last_name: props.employee?.last_name ?? null,
        phone: props.employee?.phone ?? null,
        position: props.employee?.position ?? null,
        role_name: props.employee?.role_name ?? '',
        permissions: [...(props.employee?.permissions ?? [])],
        employment: null,
        company_ico: props.employee?.company_ico ?? null,
        company_name: props.employee?.company_name ?? null,
        birth_date: props.personal?.birth_date ?? null,
        national_id: props.personal?.national_id ?? null,
        street: props.personal?.street ?? null,
        city: props.personal?.city ?? null,
        postal_code: props.personal?.postal_code ?? null,
    };
}

const form = useForm<EmployeeFormData>(
    isEditing.value ? 'put' : 'post',
    isEditing.value ? `/employees/${props.employee!.id}` : '/employees',
    initialData(),
);

form.transform((data: EmployeeFormData) =>
    isEditing.value
        ? employeeUpdatePayload(data, hasPersonalSection.value)
        : employeeStorePayload(data, hasPersonalSection.value),
);

useDependentValidation(form, {
    has_app_access: ['email', 'role_name', 'first_name', 'last_name'],
    employee_type: ['company_ico'],
});

const hasLogin = computed(() => (isEditing.value ? props.employee!.has_access : form.has_app_access));
const canManageAccess = computed(() => props.context.can_manage_access);
const emailField = computed<'email' | 'contact_email'>(() =>
    isEditing.value && !props.employee!.has_access ? 'contact_email' : 'email',
);
const emailLocked = computed(() => isEditing.value && props.employee!.has_access);

const grantableGroups = computed<App.Data.PermissionGroupData[]>(() =>
    props.context.permission_groups
        .map((g) => ({ ...g, permissions: g.permissions.filter((p) => allows(p.name)) }))
        .filter((g) => g.permissions.length > 0),
);

function updatePermissions(value: string[]): void {
    form.permissions = value;
    form.validate('permissions');
}

function updateEmployeeType(type: App.Enums.EmployeeTypeEnum): void {
    if (form.employment) {
        form.employment = { ...form.employment, employment_type: employmentTypeForEmployeeType(type) };
    }
}

function submit(): void {
    cancelPendingValidation(form);
    form.submit();
}

onBeforeUnmount(() => cancelPendingValidation(form));
</script>

<template>
    <FormProvider :form="form">
        <form novalidate class="space-y-4" @submit.prevent="submit">
            <div class="card bg-base-100">
                <div class="card-body space-y-4">
                    <h2 class="card-title">{{ t('employee_section_type') }}</h2>
                    <EmployeeTypeFields
                        :required="!isEditing"
                        :self-employed="form.employee_type === 'self_employed'"
                        @update:employee-type="updateEmployeeType"
                    />
                </div>
            </div>

            <div class="card bg-base-100">
                <div class="card-body space-y-4">
                    <h2 class="card-title">{{ t('employee_section_profile') }}</h2>
                    <EmployeeProfileFields
                        :email-field="emailField"
                        :email-is-login="hasLogin"
                        :email-locked="emailLocked"
                        :names-required="!hasLogin"
                    />
                </div>
            </div>

            <div class="card bg-base-100">
                <div class="card-body space-y-4">
                    <h2 class="card-title">{{ t('employee_section_access') }}</h2>
                    <EmployeeAccessSection
                        :employee="employee ?? null"
                        :can-manage="canManageAccess"
                        :show-role-fields="hasLogin"
                        :roles="context.roles"
                        :permission-groups="grantableGroups"
                        :permissions="form.permissions"
                        :permissions-error="form.errors.permissions ?? form.errors.roles ?? null"
                        @update:permissions="updatePermissions"
                    />
                </div>
            </div>

            <div v-if="hasPersonalSection" class="card bg-base-100">
                <div class="card-body space-y-4">
                    <h2 class="card-title">{{ t('employee_section_personal') }}</h2>
                    <EmployeePersonalFields />
                </div>
            </div>

            <div v-if="!isEditing" class="card bg-base-100">
                <div class="card-body space-y-4">
                    <h2 class="card-title">{{ t('employee_section_employment') }}</h2>
                    <EmployeeEmploymentSection
                        :employment="form.employment"
                        :employee-type="form.employee_type"
                        :errors="form.errors"
                        :disabled="form.processing"
                        @update:employment="form.employment = $event"
                    />
                </div>
            </div>

            <div v-else class="alert alert-info">
                <span>
                    {{ t('employee_employment_edit_hint') }}
                    <Link href="/contracts" class="link link-hover">{{ t('contracts') }}</Link>
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
