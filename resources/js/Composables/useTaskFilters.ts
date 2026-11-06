import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePageProps } from '@/Composables/usePageProps';
import { readSpatieQuery, visitSpatieQuery } from '@/Composables/useSpatieTableQuery';
import { TASK_PRIORITIES, TASK_STATUSES, taskPriorityKey, taskStatusKey } from '@/utils/enums';
import type { FilterConfig } from '@/types/table';

const ACTIVE_STATUS_FILTER = 'open,in_progress,waiting,nice_to_have';
const DUE_FILTERS = ['overdue', 'today', 'week', 'none'] as const;
const RELOAD_ONLY = ['view', 'tasks', 'board', 'filters'];

interface Options {
    view: () => App.Enums.TaskIndexViewEnum;
    filters: () => Record<string, unknown> | undefined;
    context: () => App.Data.Tasks.TaskFormContextData;
}

export function useTaskFilters(options: Options) {
    const { t } = useI18n();
    const pageProps = usePageProps();
    const query = ref(readSpatieQuery());

    watch(options.filters, () => {
        query.value = readSpatieQuery();
    });

    const assigneeOptions = computed(() => {
        const activeId = pageProps.value.tenant.active?.id;
        const members = activeId ? (options.context().members[activeId] ?? []) : [];

        return [
            { value: 'me', label: t('task_filter_assignee_me') },
            { value: 'none', label: t('task_filter_assignee_none') },
            ...members.map((m) => ({ value: m.id, label: m.name })),
        ];
    });

    const allDefinitions = computed<FilterConfig[]>(() => [
        { property: 'search', label: t('search'), type: 'text', placeholder: t('search'), defaultOperator: '~' },
        {
            property: 'status',
            label: t('status'),
            type: 'select',
            placeholder: t('status'),
            defaultOperator: '=',
            operators: ['='],
            options: [
                { value: ACTIVE_STATUS_FILTER, label: t('task_status_filter_active') },
                ...TASK_STATUSES.map((v) => ({ value: v, label: t(taskStatusKey(v)) })),
            ],
        },
        {
            property: 'priority',
            label: t('task_priority'),
            type: 'select',
            placeholder: t('task_priority'),
            defaultOperator: '=',
            options: TASK_PRIORITIES.map((v) => ({ value: v, label: t(taskPriorityKey(v)) })),
        },
        {
            property: 'due',
            label: t('task_due_at'),
            type: 'select',
            placeholder: t('task_due_at'),
            defaultOperator: '=',
            operators: ['='],
            options: DUE_FILTERS.map((v) => ({ value: v, label: t(`task_filter_due_${v}`) })),
        },
        {
            property: 'due_at',
            label: t('task_due_at'),
            type: 'date',
            placeholder: t('task_due_at'),
            defaultOperator: '>=',
            operators: ['>=', '<=', 'between'],
        },
        {
            property: 'assignee',
            label: t('task_assignee'),
            type: 'select',
            placeholder: t('task_assignee'),
            defaultOperator: '=',
            operators: ['='],
            options: assigneeOptions.value,
        },
        {
            property: 'kind',
            label: t('task_kind'),
            type: 'select',
            placeholder: t('task_kind'),
            defaultOperator: '=',
            options: [
                { value: 'firm', label: t('task_kind_firm') },
                { value: 'global', label: t('task_kind_global') },
            ],
        },
    ]);

    const definitions = computed(() =>
        options.view() === 'board' ? allDefinitions.value.filter((f) => f.property !== 'status') : allDefinitions.value,
    );

    const activeKeys = computed(() =>
        Object.keys(query.value.filters).filter((key) => definitions.value.some((f) => f.property === key)),
    );
    const hasActiveFilters = computed(() => activeKeys.value.length > 0);
    const chipSignature = computed(() =>
        activeKeys.value
            .filter((key) => key !== 'search')
            .sort()
            .join('|'),
    );

    function changeFilter(property: string, value: string | null): void {
        visitSpatieQuery({ [`filter[${property}]`]: value }, { only: RELOAD_ONLY });
    }

    function clearFilters(): void {
        const changes: Record<string, null> = { 'filter[search]': null };
        activeKeys.value.forEach((key) => {
            changes[`filter[${key}]`] = null;
        });
        visitSpatieQuery(changes, { only: RELOAD_ONLY });
    }

    function changeView(view: App.Enums.TaskIndexViewEnum): void {
        visitSpatieQuery({ view, page: null }, { resetPage: false });
    }

    return {
        query,
        definitions,
        reloadOnly: RELOAD_ONLY,
        hasActiveFilters,
        chipSignature,
        changeFilter,
        clearFilters,
        changeView,
    };
}
