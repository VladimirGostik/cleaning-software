<script setup lang="ts">
import { computed, onBeforeUnmount } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import FormProvider from '@/Components/Forms/FormProvider.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';
import { cancelPendingValidation } from '@/Composables/cancelPendingValidation';

interface AccessFormData {
    email: string;
    role_name: string;
    permissions: string[] | null;
}

const props = defineProps<{
    employeeId: string;
    contactEmail: string | null;
    roles: readonly App.Data.RoleListItemData[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const form = useForm<AccessFormData>('post', `/employees/${props.employeeId}/access`, {
    email: props.contactEmail ?? '',
    role_name: '',
    permissions: null,
});

const roleOptions = computed<SelectOption[]>(() => props.roles.map((r) => ({ value: r.name, label: r.name })));

const sharedError = computed(() => form.errors.roles ?? form.errors.permissions ?? null);

function submit(): void {
    cancelPendingValidation(form);
    form.submit({ preserveScroll: true, onSuccess: () => emit('close') });
}

onBeforeUnmount(() => cancelPendingValidation(form));
</script>

<template>
    <dialog class="modal modal-open">
        <div class="modal-box">
            <h3 class="text-lg font-medium">{{ t('employee_access_grant') }}</h3>

            <FormProvider :form="form">
                <form novalidate class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <TextInput
                            field="email"
                            type="email"
                            autocomplete="off"
                            required
                            :label="t('employee_login_email')"
                        />
                        <p class="text-xs text-muted">{{ t('employee_email_hint') }}</p>
                    </div>

                    <div>
                        <SelectInput
                            field="role_name"
                            required
                            :label="t('employee_role')"
                            :placeholder="t('select_role')"
                            :options="roleOptions"
                        />
                        <p class="text-xs text-muted">{{ t('employee_role_subset_hint') }}</p>
                    </div>

                    <p v-if="sharedError" class="text-error text-sm">{{ sharedError }}</p>

                    <div class="modal-action">
                        <FormActions
                            :submit-label="t('employee_access_grant')"
                            :processing="form.processing"
                            @cancel="emit('close')"
                        />
                    </div>
                </form>
            </FormProvider>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="button" @click="emit('close')">{{ t('close') }}</button>
        </form>
    </dialog>
</template>
