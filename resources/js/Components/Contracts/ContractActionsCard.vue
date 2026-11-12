<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ArrowDownTrayIcon from '~icons/material-symbols/download-outline-rounded';
import CheckCircleIcon from '~icons/material-symbols/check-circle-outline-rounded';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import TrashIcon from '~icons/material-symbols/delete-outline-rounded';
import XCircleIcon from '~icons/material-symbols/cancel-outline-rounded';

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

const hasText = computed(() => props.contract.body !== null);
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body space-y-2">
            <h2 class="card-title">{{ t('contract_section_actions') }}</h2>

            <Can v-if="props.contract.is_editable" permission="edit contracts">
                <Link
                    :href="`/contracts/${props.contract.id}/edit`"
                    class="btn btn-sm btn-neutral w-full justify-start"
                >
                    <PencilSquareIcon />
                    {{ t('edit') }}
                </Link>
            </Can>

            <Can v-if="hasText && props.contract.can_be_signed" permission="edit contracts">
                <button type="button" class="btn btn-secondary w-full justify-start" @click="emit('sign')">
                    <CheckCircleIcon />
                    {{ t('contract_action_sign') }}
                </button>
            </Can>

            <a
                v-if="props.contract.can_download_pdf"
                :href="`/contracts/${props.contract.id}/pdf`"
                target="_blank"
                rel="noopener"
                class="btn btn-sm btn-neutral w-full justify-start"
            >
                <ArrowDownTrayIcon />
                {{ t('contract_action_download_pdf') }}
            </a>

            <Can v-if="props.contract.can_be_terminated" permission="terminate contracts">
                <button type="button" class="btn btn-sm btn-error w-full justify-start" @click="emit('terminate')">
                    <XCircleIcon />
                    {{ t('contract_action_terminate') }}
                </button>
            </Can>

            <Can v-if="props.contract.is_editable" permission="delete contracts">
                <button type="button" class="btn btn-sm btn-error w-full justify-start" @click="emit('delete')">
                    <TrashIcon />
                    {{ t('delete') }}
                </button>
            </Can>
        </div>
    </div>
</template>
