export type TaskBoardColumn = App.Data.Tasks.TaskBoardColumnData;
export type PendingMoves = Record<string, App.Enums.TaskStatusEnum>;

const SHOW_ALL_KEEPS = ['search', 'priority', 'assignee', 'due', 'due_at', 'kind'] as const;

export function applyPendingMoves(columns: TaskBoardColumn[], pending: PendingMoves): TaskBoardColumn[] {
    return Object.entries(pending).reduce((current, [taskId, to]) => moveOne(current, taskId, to), columns);
}

function moveOne(columns: TaskBoardColumn[], taskId: string, to: App.Enums.TaskStatusEnum): TaskBoardColumn[] {
    const source = columns.find((column) => column.tasks.some((task) => task.id === taskId));
    const target = columns.find((column) => column.status === to);
    const task = source?.tasks.find((candidate) => candidate.id === taskId);

    if (!source || !target || !task || source.status === to) return columns;

    const moved: App.Data.Tasks.TaskListItemData = { ...task, status: to, is_active: to !== 'done' };

    return columns.map((column) => {
        if (column.status === source.status) {
            return {
                ...column,
                total: column.total - 1,
                tasks: column.tasks.filter((candidate) => candidate.id !== taskId),
            };
        }
        if (column.status === to) {
            return { ...column, total: column.total + 1, tasks: [moved, ...column.tasks] };
        }
        return column;
    });
}

export function isTaskOverdue(task: { is_active: boolean; due_at: string | null }, now: Date = new Date()): boolean {
    return task.is_active && task.due_at !== null && new Date(task.due_at) < now;
}

export function boardShowAllHref(status: App.Enums.TaskStatusEnum, filters: Record<string, string>): string {
    const params = new URLSearchParams({ view: 'table' });

    SHOW_ALL_KEEPS.forEach((key) => {
        const value = filters[key];
        if (value) params.set(`filter[${key}]`, value);
    });
    params.set('filter[status]', status);

    return `/tasks?${params.toString()}`;
}
