<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatDate } from '@/utils/date';

const props = defineProps<{
    personal: App.Data.Employees.EmployeePersonalData;
}>();

const { t } = useI18n();

const address = computed(() => {
    const cityLine = [props.personal.postal_code, props.personal.city].filter(Boolean).join(' ');
    return [props.personal.street, cityLine].filter(Boolean).join(', ');
});
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('employee_section_personal') }}</h2>

            <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <p class="text-xs text-base-content/50">{{ t('employee_birth_date') }}</p>
                    <p>{{ personal.birth_date ? formatDate(personal.birth_date) : t('empty_dash') }}</p>
                </div>

                <div>
                    <p class="text-xs text-base-content/50">{{ t('employee_national_id') }}</p>
                    <p class="tabular-nums">{{ personal.national_id_masked ?? t('empty_dash') }}</p>
                </div>

                <div class="sm:col-span-2">
                    <p class="text-xs text-base-content/50">{{ t('address') }}</p>
                    <p>{{ address || t('empty_dash') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
