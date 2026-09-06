<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { FilterConfig, FilterOperator } from '@/types/table';
import {
    defaultOperatorForType,
    formatFilterValue,
    operatorLabels,
    operatorsForType,
    parseFilterValue,
} from './filterOperators';

const props = defineProps<{
    filter: FilterConfig;
    queryValue?: string | null;
}>();

const emit = defineEmits<{
    change: [property: string, formattedValue: string | null];
    remove: [property: string];
}>();

const availableOperators = computed<FilterOperator[]>(() => {
    return props.filter.operators?.length ? props.filter.operators : operatorsForType(props.filter.type);
});

const initial = computed(() =>
    parseFilterValue(
        props.queryValue ?? null,
        props.filter.defaultOperator ?? defaultOperatorForType(props.filter.type),
    ),
);

const operator = ref<FilterOperator>(initial.value.operator);
const value = ref<string | null>(initial.value.value);

watch(
    () => props.queryValue,
    () => {
        const parsed = initial.value;
        operator.value = parsed.operator;
        value.value = parsed.value;
    },
);

function apply(nextValue = value.value, nextOperator = operator.value): void {
    emit('change', props.filter.property, formatFilterValue(nextValue, nextOperator));
}

function changeOperator(next: FilterOperator): void {
    operator.value = next;
    apply(value.value, next);
}

function changeValue(next: string | null): void {
    value.value = next;
    apply(next, operator.value);
}

const selectedValues = computed<string[]>({
    get: () => value.value?.split(',').filter(Boolean) ?? [],
    set: (next) => changeValue(next.length ? next.join(',') : null),
});

const betweenInputType = computed<'date' | 'datetime-local' | 'number' | 'text'>(() => {
    if (props.filter.type === 'date') {
        return 'date';
    }
    if (props.filter.type === 'datetime') {
        return 'datetime-local';
    }
    if (props.filter.type === 'number') {
        return 'number';
    }
    return 'text';
});

const betweenFrom = computed<string>(() => value.value?.split(',')[0] ?? '');
const betweenTo = computed<string>(() => value.value?.split(',')[1] ?? '');

function changeBetween(from: string, to: string): void {
    if (from === '' && to === '') {
        changeValue(null);
        return;
    }
    changeValue(`${from},${to}`);
}

function toggleBoolean(): void {
    if (value.value === null || value.value === '') {
        changeValue('true');
        return;
    }
    if (value.value === 'true' || value.value === '1') {
        changeValue('false');
        return;
    }
    changeValue(null);
}
</script>

<template>
    <div class="join" :data-autom="`filter-${filter.property}`">
        <select
            v-if="availableOperators.length > 1"
            class="select select-sm select-bordered join-item max-w-40"
            :value="operator"
            @change="changeOperator(($event.target as HTMLSelectElement).value as FilterOperator)"
        >
            <option v-for="op in availableOperators" :key="op" :value="op">
                {{ $t(operatorLabels[op]) }}
            </option>
        </select>

        <button v-else type="button" class="btn btn-sm join-item no-animation pointer-events-none">
            {{ filter.label }}
        </button>

        <template v-if="filter.type === 'enum' || filter.type === 'select' || filter.type === 'autocomplete'">
            <select
                v-if="filter.multiple"
                v-model="selectedValues"
                multiple
                class="select select-sm select-bordered join-item min-w-48 h-20"
            >
                <option v-for="option in filter.options ?? []" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>

            <select
                v-else
                class="select select-sm select-bordered join-item min-w-48"
                :value="value ?? ''"
                @change="changeValue(($event.target as HTMLSelectElement).value || null)"
            >
                <option value="">
                    {{ filter.placeholder ?? filter.label }}
                </option>
                <option v-for="option in filter.options ?? []" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
        </template>

        <button
            v-else-if="filter.type === 'boolean'"
            type="button"
            class="btn btn-sm join-item"
            :class="{
                'btn-success': value === 'true' || value === '1',
                'btn-error': value === 'false' || value === '0',
            }"
            @click="toggleBoolean"
        >
            {{
                value === 'true' || value === '1'
                    ? $t('yes')
                    : value === 'false' || value === '0'
                      ? $t('no')
                      : filter.label
            }}
        </button>

        <template v-else-if="operator === 'between'">
            <input
                class="input input-sm input-bordered join-item min-w-40"
                :type="betweenInputType"
                :placeholder="$t('date_from')"
                :value="betweenFrom"
                @input="changeBetween(($event.target as HTMLInputElement).value, betweenTo)"
            />
            <input
                class="input input-sm input-bordered join-item min-w-40"
                :type="betweenInputType"
                :placeholder="$t('date_to')"
                :value="betweenTo"
                @input="changeBetween(betweenFrom, ($event.target as HTMLInputElement).value)"
            />
        </template>

        <input
            v-else
            class="input input-sm input-bordered join-item min-w-48"
            :type="
                filter.type === 'number'
                    ? 'number'
                    : filter.type === 'date'
                      ? 'date'
                      : filter.type === 'datetime'
                        ? 'datetime-local'
                        : 'text'
            "
            :placeholder="filter.placeholder ?? filter.label"
            :value="value ?? ''"
            @input="changeValue(($event.target as HTMLInputElement).value || null)"
        />

        <button
            type="button"
            class="btn btn-sm btn-ghost join-item"
            :title="$t('remove_filter')"
            @click="emit('remove', filter.property)"
        >
            ✕
        </button>
    </div>
</template>
