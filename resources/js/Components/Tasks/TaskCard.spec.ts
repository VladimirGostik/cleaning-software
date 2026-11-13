import { addDays } from 'date-fns';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick } from 'vue';
import type { App as VueApp } from 'vue';

const MESSAGES: Record<string, string> = {
    task_due_today: 'Dnes',
    task_due_tomorrow: 'Zajtra',
    task_personal: 'Osobná',
};

const pageProps = vi.hoisted(() => ({ available: 1 }));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string, params?: unknown) =>
            typeof params === 'object' && params !== null && 'title' in params
                ? `${key}:${(params as { title: string }).title}`
                : (MESSAGES[key] ?? key),
        locale: { value: 'sk' },
    }),
}));
vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn(), get: vi.fn() },
    Link: {
        props: ['href'],
        setup:
            (props: { href: string }, { slots, attrs }: { slots: Record<string, () => unknown>; attrs: object }) =>
            () =>
                h('a', { href: props.href, ...attrs }, slots.default?.() as never),
    },
    usePage: () => ({
        url: '/tasks?view=board',
        props: { tenant: { active: null, available: Array.from({ length: pageProps.available }, () => ({})) } },
    }),
}));

import TaskBoardCard from './TaskBoardCard.vue';
import TaskCard from './TaskCard.vue';

let app: VueApp | null = null;

function taskItem(over: Partial<App.Data.Tasks.TaskListItemData> = {}): App.Data.Tasks.TaskListItemData {
    return {
        id: 't1',
        tenant_id: 'c1',
        tenant_name: 'Firma Alfa',
        title: 'Umyť okná',
        status: 'open',
        priority: 'normal',
        due_at: addDays(new Date(), 1).toISOString(),
        assignee_name: 'Jana Nováková',
        subject: null,
        is_global: false,
        created_at: '2026-11-01T10:00:00Z',
        comments_count: 2,
        attachments_count: 0,
        can_update: true,
        is_active: true,
        ai_run_status: null,
        ...over,
    };
}

function mountBoardCard(over: Partial<App.Data.Tasks.TaskListItemData> = {}) {
    const move = vi.fn();
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({
        render: () => h(TaskBoardCard, { task: taskItem(over), draggable: true, busy: false, onMove: move }),
    });
    app.mount(host);

    return { host, move };
}

function mountCard(over: Partial<App.Data.Tasks.TaskListItemData> = {}) {
    const toggle = vi.fn();
    const host = document.createElement('div');
    document.body.appendChild(host);
    app = createApp({ render: () => h(TaskCard, { task: taskItem(over), busy: false, onToggle: toggle }) });
    app.mount(host);

    return { host, toggle };
}

function circle(host: HTMLElement): HTMLElement | null {
    return host.querySelector<HTMLElement>('button[aria-label^="task_mark_"]');
}

describe('TaskCard', () => {
    beforeEach(() => {
        pageProps.available = 1;
    });

    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('board card: check circle, no status menu, due text, comments only', () => {
        const { host } = mountBoardCard();

        expect(circle(host)?.getAttribute('aria-label')).toBe('task_mark_done:Umyť okná');
        expect(host.querySelector('[aria-haspopup="menu"]')).toBeNull();
        expect(host.textContent).toContain('Zajtra');
        const comments = host.querySelector('[data-task-comments]')!;
        expect(comments.querySelector('[aria-hidden="true"]:not(svg)')?.textContent?.trim()).toBe('2');
        expect(comments.querySelector('.sr-only')?.textContent).toBe('task_comments_count');
        expect(host.querySelector('[data-task-attachments]')).toBeNull();
        expect(host.textContent).not.toContain('task_priority_high');
    });

    it('board card re-emits toggle as move', async () => {
        const { host, move } = mountBoardCard();

        circle(host)!.click();
        await nextTick();

        expect(move).toHaveBeenCalledWith('done');
    });

    it('without can_update shows an aria-hidden icon and no button', () => {
        const { host } = mountCard({ can_update: false });

        expect(circle(host)).toBeNull();
        expect(host.querySelector('button')).toBeNull();
        expect(host.querySelector('span[aria-hidden="true"] svg, svg[aria-hidden="true"]')).not.toBeNull();
    });

    it('done task offers reopen and emits open', async () => {
        const { host, toggle } = mountCard({ status: 'done', is_active: false });

        expect(circle(host)?.getAttribute('aria-label')).toBe('task_mark_open:Umyť okná');
        circle(host)!.click();
        await nextTick();

        expect(toggle).toHaveBeenCalledWith('open');
    });

    it('open task emits done', async () => {
        const { host, toggle } = mountCard();

        circle(host)!.click();
        await nextTick();

        expect(toggle).toHaveBeenCalledWith('done');
    });

    it('personal task shows "Osobná" even with one firm', () => {
        const { host } = mountCard({ is_global: true, tenant_id: null, tenant_name: null });

        expect(host.textContent).toContain('Osobná');
    });

    it('shows the firm name only with two or more firms', () => {
        const one = mountCard();
        expect(one.host.textContent).not.toContain('Firma Alfa');
        app?.unmount();
        document.body.innerHTML = '';

        pageProps.available = 2;
        const two = mountCard();
        expect(two.host.textContent).toContain('Firma Alfa');
        expect(two.host.querySelector('[title="Firma Alfa"]')?.className).toContain('truncate');
    });

    it('high priority shows the chip', () => {
        const { host } = mountCard({ priority: 'high' });

        expect(host.textContent).toContain('task_priority_high');
    });

    it('overdue date is in the error colour with an accessible suffix', () => {
        const { host } = mountCard({ due_at: '2020-01-05T10:00:00Z' });
        const due = host.querySelector('[data-task-due]')!;

        expect(due.className).toContain('text-error');
        expect(due.querySelector('.sr-only')?.textContent).toContain('task_due_overdue_label');
        expect(due.querySelector('[aria-hidden="true"]')?.textContent).not.toContain('task_due_overdue_label');
    });

    it('shows the attachments count when above zero', () => {
        const { host } = mountCard({ attachments_count: 3 });

        const attachments = host.querySelector('[data-task-attachments]')!;
        expect(attachments.querySelector('[aria-hidden="true"]:not(svg)')?.textContent?.trim()).toBe('3');
        expect(attachments.querySelector('.sr-only')?.textContent).toBe('task_attachments_count');
    });

    it('the title link is not draggable and the circle is not inside it', () => {
        const { host } = mountCard();
        const link = host.querySelector('a')!;

        expect(link.getAttribute('draggable')).toBe('false');
        expect(link.classList.contains('line-clamp-3')).toBe(true);
        expect(link.classList.contains('block')).toBe(false);
        expect(link.querySelector('button')).toBeNull();
        expect(host.querySelectorAll('a button, button a')).toHaveLength(0);
    });

    it('the stretched link anchors to the parent card, not to the card content root', () => {
        const { host } = mountCard();

        expect(host.firstElementChild!.classList.contains('relative')).toBe(false);
        expect(host.querySelector('a')!.className).toContain('after:inset-0');
    });

    it('board article is the positioned anchor and keeps the circle above the link', () => {
        const { host } = mountBoardCard();

        expect(host.querySelector('article')!.classList.contains('relative')).toBe(true);
        expect(circle(host)!.className).toContain('z-10');
    });
});
