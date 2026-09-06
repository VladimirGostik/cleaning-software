<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    employee: App.Data.Employees.EmployeeDetailData;
}>();

const { t } = useI18n();

const showUserName = computed(() => props.employee.user_name !== props.employee.display_name);
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-4">
            <h2 class="card-title text-base">{{ t('employee_section_profile') }}</h2>

            <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div>
                    <p class="text-xs text-base-content/50">{{ t('email') }}</p>
                    <p>{{ props.employee.email }}</p>
                </div>

                <div>
                    <p class="text-xs text-base-content/50">{{ t('phone') }}</p>
                    <p>{{ props.employee.phone ?? t('empty_dash') }}</p>
                </div>

                <div>
                    <p class="text-xs text-base-content/50">{{ t('position') }}</p>
                    <p>{{ props.employee.position ?? t('empty_dash') }}</p>
                </div>

                <div v-if="showUserName">
                    <p class="text-xs text-base-content/50">{{ t('employee_user_name') }}</p>
                    <p>{{ props.employee.user_name }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
