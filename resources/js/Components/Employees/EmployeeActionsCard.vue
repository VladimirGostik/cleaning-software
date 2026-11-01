<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowPathIcon from '~icons/material-symbols/autorenew-outline-rounded';
import ArrowsRightLeftIcon from '~icons/material-symbols/swap-horiz-outline-rounded';
import NoSymbolIcon from '~icons/material-symbols/block-outline-rounded';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';

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
            <Link
                v-if="props.canUpdate"
                :href="`/employees/${props.employee.id}/edit`"
                class="btn btn-sm btn-neutral w-full justify-start"
            >
                <PencilSquareIcon class="size-5" />
                {{ t('edit') }}
            </Link>

            <button
                v-if="props.canAssignRole"
                type="button"
                class="btn btn-sm btn-neutral w-full justify-start"
                @click="emit('changeRole')"
            >
                <ArrowsRightLeftIcon class="size-5" />
                {{ t('employee_change_role') }}
            </button>

            <button
                v-if="props.canReactivate"
                type="button"
                class="btn btn-sm btn-primary w-full justify-start"
                @click="emit('reactivate')"
            >
                <ArrowPathIcon class="size-5" />
                {{ t('employee_reactivate') }}
            </button>

            <button
                v-if="props.canDeactivate"
                type="button"
                class="btn btn-sm btn-error w-full justify-start"
                @click="emit('deactivate')"
            >
                <NoSymbolIcon class="size-5" />
                {{ t('employee_deactivate') }}
            </button>
        </div>
    </div>
</template>
