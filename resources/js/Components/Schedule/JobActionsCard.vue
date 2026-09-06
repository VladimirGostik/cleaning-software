<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { PencilSquareIcon, XCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    job: App.Data.Schedule.JobDetailData;
}>();

const emit = defineEmits<{
    cancel: [];
}>();

const { t } = useI18n();

const hasActions = computed(() => props.job.can.update || props.job.can.cancel);
</script>

<template>
    <div v-if="hasActions" class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-2">
            <h2 class="card-title text-base">{{ t('schedule_section_actions') }}</h2>

            <a v-if="props.job.can.update" :href="`/jobs/${props.job.id}/edit`" class="btn btn-sm w-full justify-start">
                <PencilSquareIcon class="size-4" />
                {{ t('edit') }}
            </a>

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
