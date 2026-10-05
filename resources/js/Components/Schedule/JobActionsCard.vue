<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    CheckCircleIcon,
    HandThumbDownIcon,
    PencilSquareIcon,
    PlayCircleIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';

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
    <div v-if="hasActions" class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-2">
            <h2 class="card-title text-base">{{ t('schedule_section_actions') }}</h2>

            <Link
                v-if="props.job.can.update"
                :href="`/jobs/${props.job.id}/edit`"
                class="btn btn-sm w-full justify-start"
            >
                <PencilSquareIcon class="size-4" />
                {{ t('edit') }}
            </Link>

            <button
                v-if="props.job.can.start"
                type="button"
                class="btn btn-sm w-full justify-start text-info"
                @click="emit('start')"
            >
                <PlayCircleIcon class="size-4" />
                {{ t('schedule_action_start') }}
            </button>

            <button
                v-if="props.job.can.complete"
                type="button"
                class="btn btn-sm w-full justify-start text-success"
                @click="emit('complete')"
            >
                <CheckCircleIcon class="size-4" />
                {{ t('schedule_action_complete') }}
            </button>

            <button
                v-if="props.job.can.unapprove"
                type="button"
                class="btn btn-sm w-full justify-start text-error"
                @click="emit('unapprove')"
            >
                <HandThumbDownIcon class="size-4" />
                {{ t('schedule_action_unapprove') }}
            </button>

            <button
                v-if="props.job.can.cancel"
                type="button"
                class="btn btn-sm w-full justify-start text-warning"
                @click="emit('cancel')"
            >
                <XCircleIcon class="size-4" />
                {{ t('schedule_action_cancel') }}
            </button>
        </div>
    </div>
</template>
