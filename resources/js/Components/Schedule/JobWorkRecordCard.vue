<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import DeleteIcon from '~icons/material-symbols/delete-outline-rounded';

import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import WorkRecordFormDrawer from './WorkRecordFormDrawer.vue';
import WorkRecordPhotos from './WorkRecordPhotos.vue';
import { useWorkRecordDelete } from '@/Composables/useWorkRecordDelete';
import { formatDatetime } from '@/utils/date';
import { formatAmount } from '@/utils/workRecords';

defineProps<{
    record: App.Data.Schedule.JobWorkRecordData;
    objectId: string;
    items: App.Data.Objects.ObjectVisitItemData[];
    members: App.Data.Contracts.MembershipOptionData[];
}>();

const { t, locale } = useI18n();

const drawerOpen = ref(false);
const deletion = useWorkRecordDelete();
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="card-title">{{ t('work_record_card_title') }}</h2>
                <div v-if="record.can_update || record.can_delete" class="flex gap-1">
                    <button
                        v-if="record.can_update"
                        type="button"
                        class="btn btn-ghost btn-sm btn-square"
                        :aria-label="t('work_record_edit_title')"
                        @click="drawerOpen = true"
                    >
                        <PencilSquareIcon />
                    </button>
                    <button
                        v-if="record.can_delete"
                        type="button"
                        class="btn btn-ghost btn-sm btn-square"
                        :aria-label="t('work_record_delete')"
                        @click="deletion.openModal(record)"
                    >
                        <DeleteIcon />
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-xs text-muted">{{ t('work_record_time') }}</p>
                    <p class="tabular-nums">{{ formatDatetime(record.performed_at) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted">{{ t('work_record_employee') }}</p>
                    <p>{{ record.recorded_by_name ?? t('empty_dash') }}</p>
                </div>
            </div>

            <ul class="space-y-1 text-sm">
                <li v-for="item in record.items" :key="item.id" class="flex justify-between gap-4">
                    <span>{{ item.name }}</span>
                    <span v-if="item.unit_price !== null" class="tabular-nums text-muted">
                        {{ formatAmount(item.unit_price, locale) }}
                    </span>
                </li>
            </ul>

            <p v-if="record.note" class="whitespace-pre-wrap text-sm text-muted">{{ record.note }}</p>

            <WorkRecordPhotos :photos="record.photos" />
        </div>
    </div>

    <WorkRecordFormDrawer
        :open="drawerOpen"
        :object-id="objectId"
        :record="record"
        :items="items"
        :members="members"
        @close="drawerOpen = false"
    />

    <ConfirmDeleteModal
        :is-open="deletion.state.isOpen"
        :title="deletion.getModalTitle()"
        :description="deletion.getModalDescription()"
        :confirm-label="t('delete')"
        @cancel="deletion.closeModal"
        @confirm="deletion.confirmDelete"
    />
</template>
