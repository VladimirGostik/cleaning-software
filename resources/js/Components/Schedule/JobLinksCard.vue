<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BuildingOffice2Icon from '~icons/material-symbols/apartment-outline-rounded';
import DocumentCheckIcon from '~icons/material-symbols/task-outline-rounded';
import DocumentTextIcon from '~icons/material-symbols/description-outline-rounded';
import UserGroupIcon from '~icons/material-symbols/groups-outline-rounded';

const props = defineProps<{
    job: App.Data.Schedule.JobDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body space-y-2">
            <h2 class="card-title">{{ t('schedule_section_links') }}</h2>

            <Link
                v-if="props.job.client_id"
                :href="`/clients/${props.job.client_id}`"
                class="btn btn-sm btn-neutral h-auto min-h-9 w-full justify-start py-1.5 text-left"
            >
                <UserGroupIcon class="shrink-0" />
                {{ props.job.client_name }}
            </Link>

            <Link
                :href="`/objects/${props.job.cleaning_object_id}`"
                class="btn btn-sm btn-neutral h-auto min-h-9 w-full justify-start py-1.5 text-left"
            >
                <BuildingOffice2Icon class="shrink-0" />
                {{ props.job.object_name }}
            </Link>

            <Link
                v-if="props.job.contract_id"
                :href="`/contracts/${props.job.contract_id}`"
                class="btn btn-sm btn-neutral h-auto min-h-9 w-full justify-start py-1.5 text-left"
            >
                <DocumentCheckIcon class="shrink-0" />
                {{ props.job.contract_title ?? t('schedule_detail_contract') }}
            </Link>

            <Link
                v-if="props.job.invoice_id"
                :href="`/invoices/${props.job.invoice_id}`"
                class="btn btn-sm btn-neutral h-auto min-h-9 w-full justify-start py-1.5 text-left"
            >
                <DocumentTextIcon class="shrink-0" />
                {{ t('schedule_detail_invoice') }}
            </Link>
        </div>
    </div>
</template>
