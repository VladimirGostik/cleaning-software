<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AnchoredPopover from '@/Components/AnchoredPopover.vue';
import { KG_ICONS } from '@/icons/kegosIcons';
import type { FilterConfig } from '@/types/table';
import TableFilter from './TableFilter.vue';

const props = defineProps<{
    filter: FilterConfig;
    queryValue?: string;
    columnLabel: string;
}>();

const emit = defineEmits<{
    change: [property: string, value: string | null];
}>();

const { t } = useI18n();
const open = ref(false);

const active = computed(() => props.queryValue !== undefined && props.queryValue !== '');

function remove(property: string): void {
    emit('change', property, null);
    open.value = false;
}
</script>

<template>
    <AnchoredPopover v-model:open="open" class="p-2">
        <template #trigger="{ toggle, open: isOpen }">
            <button
                type="button"
                class="btn btn-ghost btn-xs btn-square"
                :class="{ 'text-primary': active }"
                :aria-label="t('filter_column', { column: columnLabel })"
                :title="t('filter_column', { column: columnLabel })"
                :aria-expanded="isOpen"
                @click.stop="toggle"
            >
                <KG_ICONS.adminAction.filter class="size-4" />
            </button>
        </template>

        <TableFilter
            :filter="filter"
            :query-value="queryValue"
            @change="(property, value) => emit('change', property, value)"
            @remove="remove"
        />
    </AnchoredPopover>
</template>
