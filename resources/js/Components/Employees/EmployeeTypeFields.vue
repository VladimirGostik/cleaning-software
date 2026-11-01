<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import RadioGroup, { type RadioOption } from '@/Components/Forms/RadioGroup.vue';
import TextInput from '@/Components/Forms/TextInput.vue';
import { EMPLOYEE_TYPES, employeeTypeKey } from '@/utils/enums';

defineProps<{
    required: boolean;
    selfEmployed: boolean;
}>();

const emit = defineEmits<{
    'update:employeeType': [App.Enums.EmployeeTypeEnum];
}>();

const { t } = useI18n();

const typeOptions = computed<RadioOption[]>(() =>
    EMPLOYEE_TYPES.map((v) => ({ value: v, label: t(employeeTypeKey(v)) })),
);
</script>

<template>
    <div class="space-y-4">
        <RadioGroup
            field="employee_type"
            :label="t('employee_type_choose')"
            :options="typeOptions"
            :required="required"
            @update:model-value="emit('update:employeeType', $event as App.Enums.EmployeeTypeEnum)"
        />

        <div v-if="selfEmployed" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <TextInput field="company_ico" maxlength="20" required :label="t('employee_company_ico')" />
            <TextInput field="company_name" maxlength="255" :label="t('employee_company_name')" />
        </div>
    </div>
</template>
