import { describe, expect, it } from 'vitest';
import {
    defaultRunCompanyId,
    earliestPendingExpiry,
    isTaskRunContinuePrimary,
    isTaskRunListWorthy,
    runChangeReloadProps,
    shouldShowTaskAiCard,
    taskRunActionKind,
    taskRunStatusTextKey,
} from './taskAi';

function run(overrides: Partial<App.Data.Tasks.TaskRunData> = {}): App.Data.Tasks.TaskRunData {
    return {
        id: 'r1',
        status: 'done',
        is_active: false,
        closed_task: false,
        started_by_name: 'Jana',
        tenant_id: 'c1',
        tenant_name: 'Firma',
        finished_at: null,
        actions: [],
        ...overrides,
    };
}

function thread(overrides: Partial<App.Data.Tasks.TaskThreadData> = {}): App.Data.Tasks.TaskThreadData {
    return {
        comments: [],
        latest_run: null,
        question: null,
        pending_actions: [],
        ai_available: true,
        can_comment: true,
        can_start_run: true,
        run_companies: [],
        max_body_chars: 2000,
        ...overrides,
    };
}

function pending(expiresAt: string): App.Data.Ai.PendingActionData {
    return { id: expiresAt, expires_at: expiresAt } as App.Data.Ai.PendingActionData;
}

describe('taskRunActionKind', () => {
    it('is null without the right to start', () => {
        expect(taskRunActionKind(thread({ can_start_run: false, latest_run: run() }))).toBeNull();
    });

    it('is start without a run', () => {
        expect(taskRunActionKind(thread())).toBe('start');
    });

    it('is continue on needs_input', () => {
        expect(taskRunActionKind(thread({ latest_run: run({ status: 'needs_input' }) }))).toBe('continue');
    });

    it.each(['done', 'failed', 'blocked', 'could_not_finish', 'awaiting_confirmation'] as const)(
        'is start_again after %s',
        (status) => {
            expect(taskRunActionKind(thread({ latest_run: run({ status }) }))).toBe('start_again');
        },
    );
});

describe('isTaskRunContinuePrimary', () => {
    const question = { comment_id: 'q', answer_comment_id: null, can_answer: true };

    it('is true only when the question is answered', () => {
        expect(isTaskRunContinuePrimary(thread({ question: { ...question, answered: true } }))).toBe(true);
        expect(isTaskRunContinuePrimary(thread({ question: { ...question, answered: false } }))).toBe(false);
        expect(isTaskRunContinuePrimary(thread())).toBe(false);
    });
});

describe('defaultRunCompanyId', () => {
    const a = { id: 'a', name: 'A' };
    const b = { id: 'b', name: 'B' };

    it('prefers the last run company while it is listed', () => {
        expect(defaultRunCompanyId(thread({ run_companies: [a, b], latest_run: run({ tenant_id: 'b' }) }))).toBe('b');
    });

    it('drops the last run company once it is no longer listed', () => {
        expect(defaultRunCompanyId(thread({ run_companies: [a, b], latest_run: run({ tenant_id: 'x' }) }))).toBeNull();
    });

    it('uses the only company', () => {
        expect(defaultRunCompanyId(thread({ run_companies: [a] }))).toBe('a');
    });

    it('is null for several companies without a run', () => {
        expect(defaultRunCompanyId(thread({ run_companies: [a, b] }))).toBeNull();
    });

    it('is null for an empty list', () => {
        expect(defaultRunCompanyId(thread({ latest_run: run() }))).toBeNull();
    });
});

describe('shouldShowTaskAiCard', () => {
    it('hides for a viewer with no run, no right and no pending action', () => {
        expect(shouldShowTaskAiCard(thread({ can_start_run: false }))).toBe(false);
    });

    it('shows for a run, for the right (also without a key) and for pending actions', () => {
        expect(shouldShowTaskAiCard(thread({ can_start_run: false, latest_run: run() }))).toBe(true);
        expect(shouldShowTaskAiCard(thread({ ai_available: false }))).toBe(false);
        expect(shouldShowTaskAiCard(thread({ ai_available: false, latest_run: run() }))).toBe(true);
        expect(shouldShowTaskAiCard(thread({ can_start_run: false, pending_actions: [pending('x')] }))).toBe(true);
    });
});

describe('earliestPendingExpiry', () => {
    it('is null without pending actions', () => {
        expect(earliestPendingExpiry(thread())).toBeNull();
    });

    it('returns the earliest instant across offsets', () => {
        const later = '2026-11-02T10:00:00+00:00';
        const earlier = '2026-11-02T10:30:00+02:00';

        expect(earliestPendingExpiry(thread({ pending_actions: [pending(later), pending(earlier)] }))).toBe(earlier);
    });
});

describe('runChangeReloadProps', () => {
    it('always includes selected_thread and flash', () => {
        for (const status of [null, 'running', 'failed', 'done'] as const) {
            expect(runChangeReloadProps(status)).toEqual(expect.arrayContaining(['selected_thread', 'flash']));
        }
    });

    it('adds the task and the list props only for a finished run that may have closed it', () => {
        expect(runChangeReloadProps('done')).toEqual(expect.arrayContaining(['selected_task', 'tasks', 'board']));

        for (const status of ['needs_input', null] as const) {
            const props = runChangeReloadProps(status);

            expect(props).not.toContain('selected_task');
            expect(props).not.toContain('tasks');
            expect(props).not.toContain('board');
        }
    });
});

describe('isTaskRunListWorthy', () => {
    it.each([
        ['queued', true],
        ['running', true],
        ['needs_input', true],
        ['awaiting_confirmation', true],
        ['done', false],
        ['could_not_finish', false],
        ['blocked', false],
        ['failed', false],
    ] as const)('%s -> %s', (status, expected) => {
        expect(isTaskRunListWorthy(status)).toBe(expected);
    });
});

describe('taskRunStatusTextKey', () => {
    it('distinguishes a closed done run from an open one', () => {
        expect(taskRunStatusTextKey(run({ status: 'done', closed_task: true }))).toBe('task_ai_status_done_closed');
        expect(taskRunStatusTextKey(run({ status: 'done' }))).toBe('task_ai_status_done_open');
    });

    it('maps every other status to its own key', () => {
        expect(taskRunStatusTextKey(run({ status: 'needs_input' }))).toBe('task_ai_status_needs_input');
        expect(taskRunStatusTextKey(run({ status: 'failed' }))).toBe('task_ai_status_failed');
    });
});
