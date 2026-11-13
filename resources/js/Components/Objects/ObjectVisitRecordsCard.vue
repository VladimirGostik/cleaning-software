<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AddIcon from '~icons/material-symbols/add-outline-rounded';
import ChevronLeftIcon from '~icons/material-symbols/chevron-left-outline-rounded';
import ChevronRightIcon from '~icons/material-symbols/chevron-right-outline-rounded';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import DeleteIcon from '~icons/material-symbols/delete-outline-rounded';
import EventNoteIcon from '~icons/material-symbols/event-note-outline-rounded';

import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal.vue';
import EmptyState from '@/Components/EmptyState.vue';
import WorkRecordFormDrawer from '@/Components/Schedule/WorkRecordFormDrawer.vue';
import WorkRecordPhotos from '@/Components/Schedule/WorkRecordPhotos.vue';
import { useWorkRecordDelete } from '@/Composables/useWorkRecordDelete';
import { formatDatetime } from '@/utils/date';
import { formatMonthLabel, shiftMonth } from '@/utils/date';
import { formatAmount, hasNextMonth } from '@/utils/workRecords';

const props = defineProps<{
    objectId: string;
    overview: App.Data.Objects.ObjectVisitRecordsData;
    items: App.Data.Objects.ObjectVisitItemData[];
}>();

const { t, locale } = useI18n();

const loading = ref(false);
const drawerOpen = ref(false);
const editing = ref<App.Data.Schedule.JobWorkRecordData | null>(null);

const monthLabel = computed(() => formatMonthLabel(props.overview.month, locale.value));
const nextAllowed = computed(() => hasNextMonth(props.overview.month));
const deletion = useWorkRecordDelete();

function switchMonth(delta: -1 | 1): void {
    router.reload({
        only: ['visitRecords'],
        data: { records_month: shiftMonth(props.overview.month, delta) },
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            loading.value = true;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}

function openAdd(): void {
    editing.value = null;
    drawerOpen.value = true;
}

function openEdit(record: App.Data.Schedule.JobWorkRecordData): void {
    editing.value = record;
    drawerOpen.value = true;
}
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="card-title">{{ t('work_records_title') }}</h2>
                <button
                    v-if="overview.can_add && items.length > 0"
                    type="button"
                    class="btn btn-sm btn-primary"
                    @click="openAdd"
                >
                    <AddIcon />
                    {{ t('work_records_add') }}
                </button>
            </div>

            <p v-if="overview.can_add && items.length === 0" class="text-xs text-muted">
                {{ t('work_records_define_items') }}
            </p>

            <div class="flex items-center justify-between gap-2">
                <button
                    type="button"
                    class="btn btn-ghost btn-square"
                    :disabled="loading"
                    :aria-label="t('work_records_month_previous')"
                    @click="switchMonth(-1)"
                >
                    <ChevronLeftIcon />
                </button>
                <p class="font-medium" aria-live="polite">{{ monthLabel }}</p>
                <button
                    type="button"
                    class="btn btn-ghost btn-square"
                    :disabled="loading || !nextAllowed"
                    :aria-label="t('work_records_month_next')"
                    @click="switchMonth(1)"
                >
                    <ChevronRightIcon />
                </button>
            </div>

            <div :aria-busy="loading" :class="{ 'opacity-60': loading }">
                <EmptyState
                    v-if="overview.records.length === 0"
                    :title="t('work_records_empty')"
                    :icon="EventNoteIcon"
                />

                <template v-else>
                    <ul class="space-y-1 text-sm">
                        <li
                            v-for="line in overview.summary"
                            :key="`${line.name}-${line.unit_price}`"
                            class="flex justify-between gap-4"
                        >
                            <span>
                                {{ line.name }}
                                <span class="tabular-nums text-muted">
                                    {{ line.count }}× {{ formatAmount(line.unit_price, locale) }}
                                </span>
                            </span>
                            <span class="tabular-nums">{{ formatAmount(line.total, locale) }}</span>
                        </li>
                        <li class="flex justify-between gap-4 pt-1 font-medium">
                            <span>{{ t('work_records_total') }}</span>
                            <span class="tabular-nums">{{ formatAmount(overview.total, locale) }}</span>
                        </li>
                    </ul>

                    <ul class="mt-4 space-y-4">
                        <li v-for="record in overview.records" :key="record.job_id" class="space-y-2 text-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium tabular-nums">{{ formatDatetime(record.performed_at) }}</p>
                                    <p class="text-xs text-muted">{{ record.recorded_by_name ?? t('empty_dash') }}</p>
                                </div>
                                <div v-if="record.can_update || record.can_delete" class="flex gap-1">
                                    <button
                                        v-if="record.can_update"
                                        type="button"
                                        class="btn btn-ghost btn-sm btn-square"
                                        :aria-label="t('work_record_edit_title')"
                                        @click="openEdit(record)"
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

                            <ul class="space-y-0.5">
                                <li v-for="item in record.items" :key="item.id" class="flex justify-between gap-4">
                                    <span>{{ item.name }}</span>
                                    <span v-if="item.unit_price !== null" class="tabular-nums text-muted">
                                        {{ formatAmount(item.unit_price, locale) }}
                                    </span>
                                </li>
                            </ul>

                            <p v-if="record.note" class="whitespace-pre-wrap text-muted">{{ record.note }}</p>

                            <WorkRecordPhotos :photos="record.photos" />
                        </li>
                    </ul>
                </template>
            </div>
        </div>
    </div>

    <WorkRecordFormDrawer
        :open="drawerOpen"
        :object-id="objectId"
        :record="editing"
        :items="items"
        :members="overview.members"
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
