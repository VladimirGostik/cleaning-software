<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { BuildingOffice2Icon, DocumentCheckIcon, DocumentTextIcon, UserGroupIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    job: App.Data.Schedule.JobDetailData;
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-2">
            <h2 class="card-title text-base">{{ t('schedule_section_links') }}</h2>

            <a
                v-if="props.job.client_id"
                :href="`/clients/${props.job.client_id}`"
                class="btn btn-sm w-full justify-start"
            >
                <UserGroupIcon class="size-4" />
                {{ props.job.client_name }}
            </a>

            <a :href="`/objects/${props.job.cleaning_object_id}`" class="btn btn-sm w-full justify-start">
                <BuildingOffice2Icon class="size-4" />
                {{ props.job.object_name }}
            </a>

            <a
                v-if="props.job.contract_id"
                :href="`/contracts/${props.job.contract_id}`"
                class="btn btn-sm w-full justify-start"
            >
                <DocumentCheckIcon class="size-4" />
                {{ props.job.contract_title ?? t('schedule_detail_contract') }}
            </a>

            <a
                v-if="props.job.invoice_id"
                :href="`/invoices/${props.job.invoice_id}`"
                class="btn btn-sm w-full justify-start"
            >
                <DocumentTextIcon class="size-4" />
                {{ t('schedule_detail_invoice') }}
            </a>
        </div>
    </div>
</template>
