<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import PencilSquareIcon from '~icons/material-symbols/edit-outline-rounded';
import ChecklistIcon from '~icons/material-symbols/list-alt-outline-rounded';

import EmptyState from '@/Components/EmptyState.vue';
import { formatAmount } from '@/utils/workRecords';

defineProps<{
    items: App.Data.Objects.ObjectVisitItemData[];
    canEdit: boolean;
}>();

const emit = defineEmits<{
    edit: [];
}>();

const { t, locale } = useI18n();
</script>

<template>
    <div class="card bg-base-100">
        <div class="card-body">
            <div class="flex items-center justify-between gap-2">
                <h2 class="card-title">{{ t('object_visit_items') }}</h2>
                <button v-if="canEdit" type="button" class="btn btn-sm btn-neutral" @click="emit('edit')">
                    <PencilSquareIcon />
                    {{ t('edit') }}
                </button>
            </div>

            <p class="text-xs text-muted">{{ t('object_visit_items_hint') }}</p>

            <EmptyState v-if="items.length === 0" :title="t('object_visit_items_empty')" :icon="ChecklistIcon" />

            <ul v-else class="space-y-2 text-sm">
                <li v-for="item in items" :key="item.id" class="flex justify-between gap-4">
                    <span>{{ item.name }}</span>
                    <span class="tabular-nums">{{ formatAmount(item.price, locale) }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
