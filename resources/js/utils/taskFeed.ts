type Entry = App.Data.Tasks.TaskActivityEntryData;
type Change = App.Data.Tasks.TaskActivityChangeData;
type Comment = App.Data.Tasks.TaskCommentData;

export type FeedSort = 'oldest' | 'newest';
export type FeedView = 'comments' | 'all';
export type FeedItem = { type: 'comment'; comment: Comment } | { type: 'entry'; entry: Entry } | { type: 'older' };
export type FeedValueFormat = 'status' | 'priority' | 'datetime' | 'assignee' | 'text';

export interface FeedMessage {
    key: string;
    params: Record<string, string>;
}

export interface FeedChangeLine {
    key: string;
    fieldKey: string;
    format: FeedValueFormat;
    from: string | null;
    to: string | null;
}

const FIELD_KEYS: Record<App.Enums.TaskActivityFieldEnum, string> = {
    title: 'task_title',
    description: 'description',
    status: 'status',
    priority: 'task_priority',
    due_at: 'task_due_at',
    remind_at: 'task_remind_at',
    assignee: 'task_assignee',
    subject: 'task_subject',
};

const FIELD_FORMATS: Record<App.Enums.TaskActivityFieldEnum, FeedValueFormat> = {
    title: 'text',
    description: 'text',
    status: 'status',
    priority: 'priority',
    due_at: 'datetime',
    remind_at: 'datetime',
    assignee: 'assignee',
    subject: 'text',
};

export function createdEntry(task: App.Data.Tasks.TaskDetailData): Entry {
    return {
        id: 'created',
        kind: 'created',
        actor_name: task.created_by_name,
        occurred_at: task.created_at,
        changes: [],
        attachment_name: null,
    };
}

interface BuildInput {
    comments: Comment[];
    entries: Entry[];
    hasOlder: boolean;
    sort: FeedSort;
}

export function buildTaskFeed({ comments, entries, hasOlder, sort }: BuildInput): FeedItem[] {
    const dated = [
        ...entries.map((entry) => ({
            item: { type: 'entry', entry } as FeedItem,
            time: Date.parse(entry.occurred_at),
            rank: 0,
        })),
        ...comments.map((comment) => ({
            item: { type: 'comment', comment } as FeedItem,
            time: Date.parse(comment.created_at),
            rank: 1,
        })),
    ];

    const ascending = dated
        .map((row, index) => ({ ...row, index }))
        .sort((a, b) => a.time - b.time || a.rank - b.rank || a.index - b.index);
    const items = ascending.map((row) => row.item);

    if (sort === 'newest') {
        items.reverse();
    }

    if (!hasOlder) {
        return items;
    }

    return sort === 'oldest' ? [{ type: 'older' }, ...items] : [...items, { type: 'older' }];
}

export function feedEntryHeadline(entry: Entry): FeedMessage {
    const name = entry.actor_name ?? '';
    const file = entry.attachment_name;

    switch (entry.kind) {
        case 'created':
            return { key: 'task_feed_created', params: { name } };
        case 'attachment_added':
            return file === null
                ? { key: 'task_feed_attachment_added_generic', params: { name } }
                : { key: 'task_feed_attachment_added', params: { name, file } };
        case 'attachment_deleted':
            return file === null
                ? { key: 'task_feed_attachment_deleted_generic', params: { name } }
                : { key: 'task_feed_attachment_deleted', params: { name, file } };
        default: {
            const only = entry.changes.length === 1 ? entry.changes[0] : null;

            if (only?.field === 'status' && only.to === 'done') {
                return { key: 'task_feed_completed', params: { name } };
            }

            if (only?.field === 'status' && only.from === 'done') {
                return { key: 'task_feed_reopened', params: { name } };
            }

            return { key: 'task_feed_updated', params: { name } };
        }
    }
}

export function feedChangeLine(change: Change): FeedChangeLine {
    const base = {
        fieldKey: FIELD_KEYS[change.field],
        format: FIELD_FORMATS[change.field],
        from: change.from,
        to: change.to,
    };

    if (change.field === 'description' || change.field === 'subject') {
        return { ...base, key: 'task_feed_change_edited' };
    }

    if (change.to === null && (change.field === 'due_at' || change.field === 'remind_at')) {
        return { ...base, key: 'task_feed_change_cleared' };
    }

    if (change.from === null && change.to !== null) {
        return { ...base, key: 'task_feed_change_set' };
    }

    return { ...base, key: 'task_feed_change_from_to' };
}
