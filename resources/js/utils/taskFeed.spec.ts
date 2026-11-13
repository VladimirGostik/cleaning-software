import { describe, expect, it } from 'vitest';
import { buildTaskFeed, createdEntry, feedChangeLine, feedEntryHeadline } from './taskFeed';

type Entry = App.Data.Tasks.TaskActivityEntryData;
type Change = App.Data.Tasks.TaskActivityChangeData;

function entry(overrides: Partial<Entry> = {}): Entry {
    return {
        id: 'e1',
        kind: 'updated',
        actor_name: 'Jana',
        occurred_at: '2026-11-10T08:00:00+00:00',
        changes: [],
        attachment_name: null,
        ...overrides,
    };
}

function comment(id: string, createdAt: string): App.Data.Tasks.TaskCommentData {
    return {
        id,
        author_kind: 'user',
        author_name: 'Peter',
        body: 'x',
        created_at: createdAt,
        is_mine: false,
        can_delete: false,
        can_edit: false,
        edited_at: null,
        answers_comment_id: null,
    };
}

function change(field: Change['field'], from: string | null, to: string | null): Change {
    return { field, from, to };
}

const ids = (items: ReturnType<typeof buildTaskFeed>) =>
    items.map((item) => (item.type === 'entry' ? item.entry.id : item.type === 'comment' ? item.comment.id : 'older'));

describe('buildTaskFeed', () => {
    const entries = [
        entry({ id: 'e1', occurred_at: '2026-11-10T08:00:00+00:00' }),
        entry({ id: 'e2', occurred_at: '2026-11-12T08:00:00+00:00' }),
    ];
    const comments = [comment('c1', '2026-11-11T08:00:00+00:00')];

    it('merges oldest first', () => {
        expect(ids(buildTaskFeed({ comments, entries, hasOlder: false, sort: 'oldest' }))).toEqual(['e1', 'c1', 'e2']);
    });

    it('merges newest first', () => {
        expect(ids(buildTaskFeed({ comments, entries, hasOlder: false, sort: 'newest' }))).toEqual(['e2', 'c1', 'e1']);
    });

    it('puts an entry before a comment on equal timestamps', () => {
        const at = '2026-11-11T08:00:00+00:00';
        const feed = buildTaskFeed({
            comments: [comment('c1', at)],
            entries: [entry({ id: 'e1', occurred_at: at })],
            hasOlder: false,
            sort: 'oldest',
        });

        expect(ids(feed)).toEqual(['e1', 'c1']);
    });

    it('places the older marker at the oldest end for both sorts', () => {
        expect(ids(buildTaskFeed({ comments, entries, hasOlder: true, sort: 'oldest' }))[0]).toBe('older');
        expect(ids(buildTaskFeed({ comments, entries, hasOlder: true, sort: 'newest' })).at(-1)).toBe('older');
    });

    it('builds the comments tab from the created entry and the comments only', () => {
        const task = {
            created_by_name: 'Admin',
            created_at: '2026-11-01T08:00:00+00:00',
        } as App.Data.Tasks.TaskDetailData;
        const feed = buildTaskFeed({ comments, entries: [createdEntry(task)], hasOlder: false, sort: 'oldest' });

        expect(ids(feed)).toEqual(['created', 'c1']);
        expect(createdEntry(task)).toMatchObject({ kind: 'created', actor_name: 'Admin', changes: [] });
    });
});

describe('feedEntryHeadline', () => {
    it('maps created and plain updates', () => {
        expect(feedEntryHeadline(entry({ kind: 'created' }))).toEqual({
            key: 'task_feed_created',
            params: { name: 'Jana' },
        });
        expect(
            feedEntryHeadline(entry({ changes: [change('title', 'a', 'b'), change('priority', 'low', 'high')] })).key,
        ).toBe('task_feed_updated');
    });

    it('detects completed and reopened from a single status change', () => {
        expect(feedEntryHeadline(entry({ changes: [change('status', 'open', 'done')] })).key).toBe(
            'task_feed_completed',
        );
        expect(feedEntryHeadline(entry({ changes: [change('status', 'done', 'open')] })).key).toBe(
            'task_feed_reopened',
        );
        expect(
            feedEntryHeadline(entry({ changes: [change('status', 'open', 'done'), change('title', 'a', 'b')] })).key,
        ).toBe('task_feed_updated');
    });

    it('uses the generic wording without an attachment name', () => {
        expect(feedEntryHeadline(entry({ kind: 'attachment_added', attachment_name: 'a.pdf' }))).toEqual({
            key: 'task_feed_attachment_added',
            params: { name: 'Jana', file: 'a.pdf' },
        });
        expect(feedEntryHeadline(entry({ kind: 'attachment_added' })).key).toBe('task_feed_attachment_added_generic');
        expect(feedEntryHeadline(entry({ kind: 'attachment_deleted', attachment_name: 'a.pdf' })).key).toBe(
            'task_feed_attachment_deleted',
        );
        expect(feedEntryHeadline(entry({ kind: 'attachment_deleted' })).key).toBe(
            'task_feed_attachment_deleted_generic',
        );
    });

    it('keeps a null actor as an empty name for the caller to label', () => {
        expect(feedEntryHeadline(entry({ actor_name: null })).params.name).toBe('');
    });
});

describe('feedChangeLine', () => {
    it('maps every field to its label key and value format', () => {
        const fields: [Change['field'], string, string][] = [
            ['title', 'task_title', 'text'],
            ['description', 'description', 'text'],
            ['status', 'status', 'status'],
            ['priority', 'task_priority', 'priority'],
            ['due_at', 'task_due_at', 'datetime'],
            ['remind_at', 'task_remind_at', 'datetime'],
            ['assignee', 'task_assignee', 'assignee'],
            ['subject', 'task_subject', 'text'],
        ];

        for (const [field, fieldKey, format] of fields) {
            expect(feedChangeLine(change(field, 'a', 'b'))).toMatchObject({ fieldKey, format });
        }
    });

    it('uses from-to when both values are set', () => {
        expect(feedChangeLine(change('status', 'open', 'waiting'))).toMatchObject({
            key: 'task_feed_change_from_to',
            from: 'open',
            to: 'waiting',
        });
    });

    it('uses set when there was no previous value', () => {
        expect(feedChangeLine(change('due_at', null, '2026-11-20T08:00:00Z')).key).toBe('task_feed_change_set');
        expect(feedChangeLine(change('assignee', null, 'Jana')).key).toBe('task_feed_change_set');
    });

    it('uses cleared for a removed date and from-to for an unassigned person', () => {
        expect(feedChangeLine(change('due_at', '2026-11-20T08:00:00Z', null)).key).toBe('task_feed_change_cleared');
        expect(feedChangeLine(change('remind_at', '2026-11-20T08:00:00Z', null)).key).toBe('task_feed_change_cleared');
        expect(feedChangeLine(change('assignee', 'Jana', null))).toMatchObject({
            key: 'task_feed_change_from_to',
            to: null,
        });
    });

    it('uses edited for description and subject', () => {
        expect(feedChangeLine(change('description', null, null)).key).toBe('task_feed_change_edited');
        expect(feedChangeLine(change('subject', null, null)).key).toBe('task_feed_change_edited');
    });
});
