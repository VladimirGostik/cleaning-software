<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowPathIcon, ArrowsRightLeftIcon, NoSymbolIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    employee: App.Data.Employees.EmployeeDetailData;
    canUpdate: boolean;
    canDeactivate: boolean;
    canReactivate: boolean;
    canAssignRole: boolean;
}>();

const emit = defineEmits<{
    deactivate: [];
    reactivate: [];
    changeRole: [];
}>();

const { t } = useI18n();

const hasActions = computed(() => props.canUpdate || props.canDeactivate || props.canReactivate || props.canAssignRole);
</script>

<template>
    <div v-if="hasActions" class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-2">
            <a
                v-if="props.canUpdate"
                :href="`/employees/${props.employee.id}/edit`"
                class="btn btn-sm w-full justify-start"
            >
                <PencilSquareIcon class="size-4" />
                {{ t('edit') }}
            </a>

            <button
                v-if="props.canAssignRole"
                type="button"
                class="btn btn-sm w-full justify-start"
                @click="emit('changeRole')"
            >
                <ArrowsRightLeftIcon class="size-4" />
                {{ t('employee_change_role') }}
            </button>

            <button
                v-if="props.canReactivate"
                type="button"
                class="btn btn-sm btn-success w-full justify-start"
                @click="emit('reactivate')"
            >
                <ArrowPathIcon class="size-4" />
                {{ t('employee_reactivate') }}
            </button>

            <button
                v-if="props.canDeactivate"
                type="button"
                class="btn btn-sm w-full justify-start text-warning"
                @click="emit('deactivate')"
            >
                <NoSymbolIcon class="size-4" />
                {{ t('employee_deactivate') }}
            </button>
        </div>
    </div>
</template>
