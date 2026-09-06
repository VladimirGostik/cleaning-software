<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

import SelectInput, { type SelectOption } from '@/Components/Forms/SelectInput.vue';
import FormActions from '@/Components/Forms/FormActions.vue';

interface RoleFormData {
    role_name: string;
}

const props = defineProps<{
    open: boolean;
    employeeId: string | null;
    currentRole: string | null;
    roles: readonly App.Data.RoleListItemData[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

function makeForm(employeeId: string | null, currentRole: string | null) {
    return useForm('post', employeeId ? `/employees/${employeeId}/role` : '', {
        role_name: currentRole ?? '',
    } as RoleFormData);
}

const form = shallowRef(makeForm(props.employeeId, props.currentRole));

watch(
    () => props.employeeId,
    (employeeId) => {
        form.value = makeForm(employeeId, props.currentRole);
    },
);

const roleOptions = computed<SelectOption[]>(() => props.roles.map((r) => ({ value: r.name, label: r.name })));

function submit(): void {
    form.value.submit({ preserveScroll: true, onSuccess: () => emit('close') });
}
</script>

<template>
    <dialog class="modal" :class="{ 'modal-open': props.open }">
        <div class="modal-box">
            <h3 class="text-lg font-bold">{{ t('employee_change_role') }}</h3>

            <form novalidate class="mt-4 space-y-4" @submit.prevent="submit">
                <div>
                    <SelectInput
                        :model-value="form.role_name"
                        :label="t('employee_role')"
                        :placeholder="t('select_role')"
                        :options="roleOptions"
                        required
                        :error="form.errors.role_name"
                        @update:model-value="form.role_name = $event"
                    />
                    <p class="text-xs text-base-content/60">{{ t('employee_role_subset_hint') }}</p>
                </div>

                <div class="modal-action">
                    <FormActions :submit-label="t('save')" :processing="form.processing" @cancel="emit('close')" />
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button @click="emit('close')">close</button>
        </form>
    </dialog>
</template>
