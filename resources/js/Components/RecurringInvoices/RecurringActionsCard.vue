<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { PauseIcon, PencilSquareIcon, PlayIcon, TrashIcon, XCircleIcon } from '@heroicons/vue/24/outline';

import Can from '@/Components/Can.vue';

const props = defineProps<{
    recurringInvoice: App.Data.RecurringInvoices.RecurringInvoiceDetailData;
}>();

const emit = defineEmits<{
    pause: [];
    resume: [];
    cancel: [];
    delete: [];
}>();

const { t } = useI18n();

const isActive = computed(() => props.recurringInvoice.status === 'active');
const isPaused = computed(() => props.recurringInvoice.status === 'paused');
const isActiveOrPaused = computed(() => isActive.value || isPaused.value);
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body space-y-2">
            <h2 class="card-title text-base">{{ t('invoice_section_actions') }}</h2>

            <Can v-if="isActiveOrPaused" permission="edit recurring_invoices">
                <a
                    :href="`/recurring-invoices/${props.recurringInvoice.id}/edit`"
                    class="btn btn-sm w-full justify-start"
                >
                    <PencilSquareIcon class="size-4" />
                    {{ t('edit') }}
                </a>
            </Can>

            <Can v-if="isActive" permission="edit recurring_invoices">
                <button type="button" class="btn btn-sm w-full justify-start" @click="emit('pause')">
                    <PauseIcon class="size-4" />
                    {{ t('recurring_invoice_action_pause') }}
                </button>
            </Can>

            <Can v-if="isPaused" permission="edit recurring_invoices">
                <button type="button" class="btn btn-sm w-full justify-start" @click="emit('resume')">
                    <PlayIcon class="size-4" />
                    {{ t('recurring_invoice_action_resume') }}
                </button>
            </Can>

            <Can v-if="isActiveOrPaused" permission="delete recurring_invoices">
                <button type="button" class="btn btn-sm w-full justify-start text-warning" @click="emit('cancel')">
                    <XCircleIcon class="size-4" />
                    {{ t('recurring_invoice_action_cancel') }}
                </button>
            </Can>

            <Can permission="delete recurring_invoices">
                <button type="button" class="btn btn-sm w-full justify-start text-error" @click="emit('delete')">
                    <TrashIcon class="size-4" />
                    {{ t('delete') }}
                </button>
            </Can>
        </div>
    </div>
</template>
