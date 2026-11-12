<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CheckCircleIcon from '~icons/material-symbols/check-circle-outline-rounded';
import HandThumbDownIcon from '~icons/material-symbols/thumb-down-outline-rounded';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import PlayCircleIcon from '~icons/material-symbols/play-circle-outline-rounded';
import XCircleIcon from '~icons/material-symbols/cancel-outline-rounded';

const props = defineProps<{
    job: App.Data.Schedule.JobDetailData;
}>();

const emit = defineEmits<{
    cancel: [];
    start: [];
    complete: [];
    unapprove: [];
}>();

const { t } = useI18n();

const hasActions = computed(
    () =>
        props.job.can.update ||
        props.job.can.cancel ||
        props.job.can.start ||
        props.job.can.complete ||
        props.job.can.unapprove,
);
</script>

<template>
    <div v-if="hasActions" class="card bg-base-100">
        <div class="card-body space-y-2">
            <h2 class="card-title">{{ t('schedule_section_actions') }}</h2>

            <Link
                v-if="props.job.can.update"
                :href="`/jobs/${props.job.id}/edit`"
                class="btn btn-sm btn-neutral w-full justify-start"
            >
                <PencilSquareIcon />
                {{ t('edit') }}
            </Link>

            <button
                v-if="props.job.can.start"
                type="button"
                class="btn btn-sm btn-primary w-full justify-start"
                @click="emit('start')"
            >
                <PlayCircleIcon />
                {{ t('schedule_action_start') }}
            </button>

            <button
                v-if="props.job.can.complete"
                type="button"
                class="btn btn-sm btn-primary w-full justify-start"
                @click="emit('complete')"
            >
                <CheckCircleIcon />
                {{ t('schedule_action_complete') }}
            </button>

            <button
                v-if="props.job.can.unapprove"
                type="button"
                class="btn btn-sm btn-error w-full justify-start"
                @click="emit('unapprove')"
            >
                <HandThumbDownIcon />
                {{ t('schedule_action_unapprove') }}
            </button>

            <button
                v-if="props.job.can.cancel"
                type="button"
                class="btn btn-sm btn-error w-full justify-start"
                @click="emit('cancel')"
            >
                <XCircleIcon />
                {{ t('schedule_action_cancel') }}
            </button>
        </div>
    </div>
</template>
