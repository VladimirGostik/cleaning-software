<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import TableFilter from './TableFilter.vue';
import TableSearch from './TableSearch.vue';
import type { FilterConfig, FilterState, TableTabsConfig } from '@/types/table';
import { defaultOperatorForType, parseFilterValue } from './filterOperators';

const props = withDefaults(
    defineProps<{
        filters?: FilterConfig[];
        tabs?: TableTabsConfig;
        queryFilters?: Record<string, string>;
        disableSearch?: boolean;
        searchableAttributes?: string[];
    }>(),
    {
        filters: () => [],
        tabs: undefined,
        queryFilters: () => ({}),
        disableSearch: false,
        searchableAttributes: () => [],
    },
);

const emit = defineEmits<{
    change: [property: string, value: string | null];
    clear: [];
}>();

function makeState(config: FilterConfig): FilterState {
    const parsed = parseFilterValue(
        props.queryFilters[config.property] ?? null,
        config.defaultOperator ?? defaultOperatorForType(config.type),
    );
    return { ...config, id: window.crypto.randomUUID(), value: parsed.value, operator: parsed.operator };
}

const activeFilters = ref<FilterState[]>(
    props.filters.filter((f) => props.queryFilters[f.property] !== undefined).map(makeState),
);
const search = ref(props.queryFilters.search ?? '');

watch(
    () => props.queryFilters,
    (next) => {
        search.value = next.search ?? '';
        const existing = new Set(activeFilters.value.map((f) => f.property));
        props.filters.forEach((filter) => {
            if (next[filter.property] !== undefined && !existing.has(filter.property)) {
                activeFilters.value.push(makeState(filter));
            }
        });
    },
    { deep: true },
);

const availableFilters = computed(() =>
    props.filters.filter((filter) => !activeFilters.value.some((active) => active.property === filter.property)),
);
const hasOptionalFilters = computed(() => activeFilters.value.some((f) => f.clearable !== false) || !!search.value);
const activeTab = computed(() =>
    props.tabs?.property ? (props.queryFilters[props.tabs.property] ?? props.tabs.defaultTab ?? 'all') : 'all',
);

function addFilter(property: string) {
    const config = props.filters.find((filter) => filter.property === property);
    if (!config) return;
    activeFilters.value.push(makeState(config));
    emit('change', config.property, null);
}

function removeFilter(property: string) {
    activeFilters.value = activeFilters.value.filter((filter) => filter.property !== property);
    emit('change', property, null);
}

function clearAll() {
    activeFilters.value = activeFilters.value.filter((filter) => filter.clearable === false);
    search.value = '';
    emit('clear');
}

function handleTab(value: string) {
    if (!props.tabs) return;
    emit('change', props.tabs.property, value === 'all' ? null : value);
    const tab = props.tabs.options.find((option) => option.value === value);
    tab?.clearFilters?.forEach(removeFilter);
    Object.entries(tab?.filters ?? {}).forEach(([property, filterValue]) => emit('change', property, filterValue));
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div v-if="tabs" role="tablist" class="tabs tabs-border overflow-x-auto">
            <button
                v-for="tab in tabs.options"
                :key="tab.value"
                type="button"
                role="tab"
                class="tab"
                :class="{ 'tab-active': activeTab === tab.value }"
                @click="handleTab(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="flex flex-wrap items-start gap-2">
            <TableSearch
                v-model="search"
                :disabled="disableSearch"
                :searchable-attributes="searchableAttributes"
                @update:model-value="emit('change', 'search', $event || null)"
            />

            <TableFilter
                v-for="filter in activeFilters"
                :key="filter.id"
                :filter="filter"
                :query-value="queryFilters[filter.property]"
                @change="(property, value) => emit('change', property, value)"
                @remove="removeFilter"
            />

            <div v-if="availableFilters.length" class="dropdown dropdown-bottom">
                <button tabindex="0" type="button" class="btn btn-sm btn-outline" data-autom="button-add-filter">
                    {{ $t('filter') }}
                </button>
                <ul
                    tabindex="0"
                    class="dropdown-content menu bg-base-100 rounded-box z-20 w-56 p-2 shadow border border-base-300"
                >
                    <li v-for="filter in availableFilters" :key="filter.property">
                        <button type="button" @click="addFilter(filter.property)">{{ filter.label }}</button>
                    </li>
                </ul>
            </div>

            <button v-if="hasOptionalFilters" type="button" class="btn btn-sm btn-ghost" @click="clearAll">
                {{ $t('clear_filters') }}
            </button>
        </div>
    </div>
</template>
