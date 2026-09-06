import type { FilterOperator, FilterType } from '@/types/table';

export const operatorLabels: Record<FilterOperator, string> = {
    '=': 'filter_op_eq',
    '!=': 'filter_op_neq',
    '<': 'filter_op_lt',
    '<=': 'filter_op_lte',
    '>': 'filter_op_gt',
    '>=': 'filter_op_gte',
    between: 'filter_op_between',
    '~': 'filter_op_contains',
};

export const operatorPrefixes: Record<FilterOperator, string> = {
    '=': '',
    '~': '~:',
    '!=': '!=:',
    '<': '<:',
    '<=': '<=:',
    '>': '>:',
    '>=': '>=:',
    between: 'between:',
};

export const operatorsByType: Record<FilterType, FilterOperator[]> = {
    string: ['~', '=', '!='],
    text: ['~', '=', '!='],

    number: ['=', '!=', '<', '<=', '>', '>=', 'between'],

    boolean: ['='],

    date: ['=', '<', '<=', '>', '>=', 'between'],
    datetime: ['=', '<', '<=', '>', '>=', 'between'],

    enum: ['=', '!='],
    select: ['=', '!='],
    autocomplete: ['=', '!='],
};

export function normalizeFilterType(type: FilterType): FilterType {
    if (type === 'text') {
        return 'string';
    }

    if (type === 'select') {
        return 'enum';
    }

    return type;
}

export function defaultOperatorForType(type: FilterType): FilterOperator {
    const normalized = normalizeFilterType(type);

    if (normalized === 'string') {
        return '~';
    }

    return '=';
}

export function operatorsForType(type: FilterType): FilterOperator[] {
    return operatorsByType[type] ?? operatorsByType[normalizeFilterType(type)] ?? ['='];
}

export function formatFilterValue(value: string | null, operator: FilterOperator): string | null {
    if (value === null || value === '') {
        return null;
    }

    return `${operatorPrefixes[operator]}${value}`;
}

export function parseFilterValue(
    raw: string | null,
    fallback: FilterOperator,
): { value: string | null; operator: FilterOperator } {
    if (!raw) {
        return {
            value: null,
            operator: fallback,
        };
    }

    const found = (Object.entries(operatorPrefixes) as [FilterOperator, string][])
        .filter(([, prefix]) => prefix.length > 0)
        .sort((a, b) => b[1].length - a[1].length)
        .find(([, prefix]) => raw.startsWith(prefix));

    if (!found) {
        return {
            value: raw,
            operator: fallback,
        };
    }

    const [operator, prefix] = found;

    return {
        value: raw.slice(prefix.length),
        operator,
    };
}
