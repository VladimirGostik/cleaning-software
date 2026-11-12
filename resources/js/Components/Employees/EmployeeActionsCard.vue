<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowPathIcon from '~icons/material-symbols/autorenew-outline-rounded';
import ArrowsRightLeftIcon from '~icons/material-symbols/swap-horiz-outline-rounded';
import KeyIcon from '~icons/material-symbols/key-outline-rounded';
import KeyOffIcon from '~icons/material-symbols/key-off-outline-rounded';
import ResendIcon from '~icons/material-symbols/forward-to-inbox-outline-rounded';
import NoSymbolIcon from '~icons/material-symbols/block-outline-rounded';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';

const props = defineProps<{
    employee: App.Data.Employees.EmployeeDetailData;
    canUpdate: boolean;
    canDeactivate: boolean;
    canReactivate: boolean;
    canAssignRole: boolean;
    canGrantAccess: boolean;
    canRevokeAccess: boolean;
    canResendInvitation: boolean;
    resending?: boolean;
}>();

const emit = defineEmits<{
    deactivate: [];
    reactivate: [];
    changeRole: [];
    grantAccess: [];
    revokeAccess: [];
    resendInvitation: [];
}>();

const { t } = useI18n();

const hasActions = computed(
    () =>
        props.canUpdate ||
        props.canDeactivate ||
        props.canReactivate ||
        props.canAssignRole ||
        props.canGrantAccess ||
        props.canRevokeAccess ||
        props.canResendInvitation,
);
</script>

<template>
    <div v-if="hasActions" class="card bg-base-100">
        <div class="card-body space-y-2">
            <Link
                v-if="props.canUpdate"
                :href="`/employees/${props.employee.id}/edit`"
                class="btn btn-sm btn-neutral w-full justify-start"
            >
                <PencilSquareIcon />
                {{ t('edit') }}
            </Link>

            <button
                v-if="props.canAssignRole"
                type="button"
                class="btn btn-sm btn-neutral w-full justify-start"
                @click="emit('changeRole')"
            >
                <ArrowsRightLeftIcon />
                {{ t('employee_change_role') }}
            </button>

            <button
                v-if="props.canGrantAccess"
                type="button"
                class="btn btn-sm btn-primary w-full justify-start"
                @click="emit('grantAccess')"
            >
                <KeyIcon />
                {{ t('employee_access_grant') }}
            </button>

            <button
                v-if="props.canResendInvitation"
                type="button"
                class="btn btn-sm btn-neutral w-full justify-start"
                :disabled="props.resending"
                @click="emit('resendInvitation')"
            >
                <ResendIcon />
                {{ t('employee_access_resend') }}
            </button>

            <button
                v-if="props.canReactivate"
                type="button"
                class="btn btn-sm btn-primary w-full justify-start"
                @click="emit('reactivate')"
            >
                <ArrowPathIcon />
                {{ t('employee_reactivate') }}
            </button>

            <button
                v-if="props.canRevokeAccess"
                type="button"
                class="btn btn-sm btn-error w-full justify-start"
                @click="emit('revokeAccess')"
            >
                <KeyOffIcon />
                {{ t('employee_access_revoke') }}
            </button>

            <button
                v-if="props.canDeactivate"
                type="button"
                class="btn btn-sm btn-error w-full justify-start"
                @click="emit('deactivate')"
            >
                <NoSymbolIcon />
                {{ t('employee_deactivate') }}
            </button>
        </div>
    </div>
</template>
