<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import TextInput from '@/Components/Forms/TextInput.vue';
import DateInput from '@/Components/Forms/DateInput.vue';
import { useFormContext } from '@/Components/Forms/useFormContext';

const { t } = useI18n();
const form = useFormContext();

function updateBirthDate(value: string | null): void {
    if (!form) return;
    (form as Record<string, unknown>).birth_date = value;
}
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <DateInput
            :model-value="form ? ((form as Record<string, unknown>).birth_date as string | null) : null"
            :label="t('employee_birth_date')"
            :error="form?.errors.birth_date"
            @update:model-value="updateBirthDate"
        />

        <div>
            <TextInput field="national_id" autocomplete="off" maxlength="12" :label="t('employee_national_id')" />
            <p class="text-xs text-muted">{{ t('employee_national_id_hint') }}</p>
        </div>

        <div class="md:col-span-2">
            <TextInput field="street" maxlength="255" :label="t('street')" />
        </div>
        <TextInput field="city" maxlength="100" :label="t('city')" />
        <TextInput field="postal_code" maxlength="20" :label="t('postal_code')" />
    </div>
</template>
