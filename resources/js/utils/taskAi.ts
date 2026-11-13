export type TaskRunActionKind = 'start' | 'continue' | 'start_again';

type Thread = App.Data.Tasks.TaskThreadData;
type Run = App.Data.Tasks.TaskRunData;

const LIST_WORTHY: readonly App.Enums.TaskRunStatusEnum[] = [
    'queued',
    'running',
    'needs_input',
    'awaiting_confirmation',
];

export function taskRunActionKind(thread: Thread): TaskRunActionKind | null {
    if (!thread.can_start_run) {
        return null;
    }

    if (thread.latest_run === null) {
        return 'start';
    }

    return thread.latest_run.status === 'needs_input' ? 'continue' : 'start_again';
}

export function isTaskRunContinuePrimary(thread: Thread): boolean {
    return thread.question?.answered === true;
}

export function defaultRunCompanyId(thread: Thread): string | null {
    const lastId = thread.latest_run?.tenant_id;

    if (lastId !== undefined && thread.run_companies.some((company) => company.id === lastId)) {
        return lastId;
    }

    return thread.run_companies.length === 1 ? thread.run_companies[0].id : null;
}

export function shouldShowTaskAiCard(thread: Thread): boolean {
    if (thread.latest_run !== null || thread.pending_actions.length > 0) {
        return true;
    }

    return thread.ai_available && thread.can_start_run;
}

export function earliestPendingExpiry(thread: Thread): string | null {
    const dates = thread.pending_actions.map((action) => action.expires_at);

    return dates.reduce<string | null>(
        (earliest, date) => (earliest === null || Date.parse(date) < Date.parse(earliest) ? date : earliest),
        null,
    );
}

export function runChangeReloadProps(status: App.Enums.TaskRunStatusEnum | null): string[] {
    return status === 'done'
        ? ['selected_task', 'selected_thread', 'tasks', 'board', 'flash']
        : ['selected_thread', 'flash'];
}

export function isTaskRunListWorthy(status: App.Enums.TaskRunStatusEnum): boolean {
    return LIST_WORTHY.includes(status);
}

export function taskRunStatusTextKey(run: Run): string {
    if (run.status === 'done') {
        return run.closed_task ? 'task_ai_status_done_closed' : 'task_ai_status_done_open';
    }

    return `task_ai_status_${run.status}`;
}
