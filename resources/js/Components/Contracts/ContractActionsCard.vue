<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import {
    ArrowDownTrayIcon,
    CheckCircleIcon,
    PencilSquareIcon,
    TrashIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';

import Can from '@/Components/Can.vue';

const props = defineProps<{
    contract: App.Data.Contracts.ContractDetailData;
}>();

const emit = defineEmits<{
    sign: [];
    terminate: [];
    delete: [];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-2">
            <h2 class="card-title text-base">{{ t('contract_section_actions') }}</h2>

            <Can v-if="props.contract.is_editable" permission="edit contracts">
                <a :href="`/contracts/${props.contract.id}/edit`" class="btn btn-sm w-full justify-start">
                    <PencilSquareIcon class="size-4" />
                    {{ t('edit') }}
                </a>
            </Can>

            <Can v-if="props.contract.can_be_signed" permission="edit contracts">
                <button type="button" class="btn btn-sm btn-success w-full justify-start" @click="emit('sign')">
                    <CheckCircleIcon class="size-4" />
                    {{ t('contract_action_sign') }}
                </button>
            </Can>

            <a
                :href="`/contracts/${props.contract.id}/pdf`"
                target="_blank"
                rel="noopener"
                class="btn btn-sm w-full justify-start"
            >
                <ArrowDownTrayIcon class="size-4" />
                {{ t('contract_action_download_pdf') }}
            </a>

            <Can v-if="props.contract.can_be_terminated" permission="terminate contracts">
                <button type="button" class="btn btn-sm w-full justify-start text-warning" @click="emit('terminate')">
                    <XCircleIcon class="size-4" />
                    {{ t('contract_action_terminate') }}
                </button>
            </Can>

            <Can v-if="props.contract.is_editable" permission="delete contracts">
                <button type="button" class="btn btn-sm w-full justify-start text-error" @click="emit('delete')">
                    <TrashIcon class="size-4" />
                    {{ t('delete') }}
                </button>
            </Can>
        </div>
    </div>
</template>
