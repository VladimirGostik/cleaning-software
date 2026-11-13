import { describe, expect, it } from 'vitest';
import { applyPendingMoves, boardShowAllHref, formatTaskDue, isTaskOverdue } from './taskBoard';

function task(id: string, status: App.Enums.TaskStatusEnum): App.Data.Tasks.TaskListItemData {
    return {
        id,
        tenant_id: 'c1',
        tenant_name: 'Firma',
        title: `Task ${id}`,
        status,
        priority: 'normal',
        due_at: null,
        assignee_name: null,
        subject: null,
        is_global: false,
        created_at: '2026-11-01T10:00:00Z',
        comments_count: 0,
        attachments_count: 0,
        can_update: true,
        is_active: status !== 'done',
        ai_run_status: null,
    };
}

function board(): App.Data.Tasks.TaskBoardColumnData[] {
    return [
        { status: 'open', total: 5, tasks: [task('a', 'open'), task('b', 'open')], has_more: false },
        { status: 'waiting', total: 1, tasks: [task('c', 'waiting')], has_more: false },
        { status: 'done', total: 9, tasks: [task('d', 'done')], has_more: false },
    ];
}

describe('applyPendingMoves', () => {
    it('moves a card to the top of the target column and adjusts both totals', () => {
        const result = applyPendingMoves(board(), { b: 'waiting' });

        expect(result[0]?.tasks.map((t) => t.id)).toEqual(['a']);
        expect(result[0]?.total).toBe(4);
        expect(result[1]?.tasks.map((t) => t.id)).toEqual(['b', 'c']);
        expect(result[1]?.total).toBe(2);
        expect(result[1]?.tasks[0]?.status).toBe('waiting');
    });

    it('marks a card moved to done as inactive', () => {
        const result = applyPendingMoves(board(), { a: 'done' });

        expect(result[2]?.tasks[0]).toMatchObject({ id: 'a', status: 'done', is_active: false });
    });

    it('leaves the board unchanged for the current status', () => {
        const input = board();

        expect(applyPendingMoves(input, { a: 'open' })).toEqual(board());
    });

    it('ignores a task that is not on the board', () => {
        expect(applyPendingMoves(board(), { zzz: 'waiting' })).toEqual(board());
    });

    it('does not mutate its input', () => {
        const input = board();
        applyPendingMoves(input, { a: 'done' });

        expect(input).toEqual(board());
    });
});

describe('boardShowAllHref', () => {
    it('keeps the shared filters, forces the table view and the column status, drops the rest', () => {
        const href = boardShowAllHref('done', {
            search: 'okno',
            priority: 'high',
            assignee: 'me',
            due: 'week',
            kind: 'firm',
            status: 'open',
            page: '3',
            unrelated: 'x',
        });
        const params = new URL(href, 'http://localhost').searchParams;

        expect(href.startsWith('/tasks?')).toBe(true);
        expect(params.get('view')).toBe('table');
        expect(params.get('filter[status]')).toBe('done');
        expect(params.get('filter[search]')).toBe('okno');
        expect(params.get('filter[priority]')).toBe('high');
        expect(params.get('filter[assignee]')).toBe('me');
        expect(params.get('filter[due]')).toBe('week');
        expect(params.get('filter[kind]')).toBe('firm');
        expect(params.has('page')).toBe(false);
        expect(params.has('filter[unrelated]')).toBe(false);
    });
});

describe('isTaskOverdue', () => {
    const now = new Date('2026-11-05T12:00:00Z');

    it('is overdue for an active task past its due date', () => {
        expect(isTaskOverdue({ is_active: true, due_at: '2026-11-05T11:00:00Z' }, now)).toBe(true);
    });

    it('is not overdue for a future date, no date or a finished task', () => {
        expect(isTaskOverdue({ is_active: true, due_at: '2026-11-05T13:00:00Z' }, now)).toBe(false);
        expect(isTaskOverdue({ is_active: true, due_at: null }, now)).toBe(false);
        expect(isTaskOverdue({ is_active: false, due_at: '2026-11-01T00:00:00Z' }, now)).toBe(false);
    });
});

describe('formatTaskDue', () => {
    const today = new Date(2026, 10, 13, 23, 30);

    it('marks today and tomorrow by local calendar day', () => {
        expect(formatTaskDue(new Date(2026, 10, 13, 8, 0).toISOString(), today).kind).toBe('today');
        expect(formatTaskDue(new Date(2026, 10, 14, 0, 5).toISOString(), today).kind).toBe('tomorrow');
    });

    it('formats a later date of this year without the year', () => {
        expect(formatTaskDue(new Date(2026, 11, 3, 9, 0).toISOString(), today)).toEqual({
            kind: 'date',
            text: '3. 12.',
        });
    });

    it('adds the year for another year', () => {
        expect(formatTaskDue(new Date(2027, 0, 5, 9, 0).toISOString(), today)).toEqual({
            kind: 'date',
            text: '5. 1. 2027',
        });
    });

    it('treats a time crossing midnight as the next day', () => {
        const justBefore = new Date(2026, 10, 13, 23, 59);
        const justAfter = new Date(2026, 10, 14, 0, 1);

        expect(formatTaskDue(justBefore.toISOString(), today).kind).toBe('today');
        expect(formatTaskDue(justAfter.toISOString(), today).kind).toBe('tomorrow');
    });
});
