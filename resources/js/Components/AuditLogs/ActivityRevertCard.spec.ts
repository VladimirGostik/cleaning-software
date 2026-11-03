import { describe, expect, it, vi } from 'vitest';
import { createApp, defineComponent, h } from 'vue';
import { createI18n } from 'vue-i18n';
import ActivityRevertCard from './ActivityRevertCard.vue';

const state = vi.hoisted(() => ({ allowed: true }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            get can() {
                return { revertAuditLogs: state.allowed };
            },
        },
    }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}));

const messages = {
    en: {
        audit_revert_action: 'Revert',
        audit_revert_reason_already_reverted: 'Already reverted.',
        audit_revert_reason_conflict: 'Conflict.',
        audit_revert_reason_batch_member_blocked: 'Batch blocked.',
        audit_revert_blocking_link: 'Open entry #{id}',
        audit_revert_conflicts_title: 'Conflicting fields',
        audit_revert_batch_hint: 'Reverts {count} related record | Reverts {count} related records',
    },
};

function baseRevert(overrides: Partial<App.Data.ActivityRevertData> = {}): App.Data.ActivityRevertData {
    return {
        status: 'available',
        reason: null,
        changes_count: 1,
        batch_size: 1,
        blocking_activity_id: null,
        reverted_at: null,
        reverted_by_name: null,
        reverted_by_activity_id: null,
        reverts_activity_id: null,
        ...overrides,
    };
}

function change(overrides: Partial<App.Data.ActivityRevertChangeData> = {}): App.Data.ActivityRevertChangeData {
    return {
        field: 'name',
        label: 'Name',
        old: 'a',
        new: 'b',
        current: 'c',
        conflict: false,
        masked: false,
        ...overrides,
    };
}

function render(
    revert: App.Data.ActivityRevertData,
    changes: App.Data.ActivityRevertChangeData[] = [],
    onRevert = vi.fn(),
): HTMLElement {
    const host = document.createElement('div');
    createApp({ render: () => h(ActivityRevertCard, { revert, changes, onRevert }) })
        .use(createI18n({ legacy: false, locale: 'en', messages, missingWarn: false, fallbackWarn: false }))
        .mount(host);
    return host;
}

describe('ActivityRevertCard', () => {
    it('emits revert from an enabled button when available and permitted', () => {
        state.allowed = true;
        const onRevert = vi.fn();
        const button = render(baseRevert(), [], onRevert).querySelector('button') as HTMLElement & {
            disabled: boolean;
        };

        expect(button.disabled).toBe(false);
        button.click();
        expect(onRevert).toHaveBeenCalledTimes(1);
    });

    it('hides the button without permission', () => {
        state.allowed = false;
        expect(render(baseRevert()).querySelector('button')).toBeNull();
    });

    it('shows a disabled button and the reason when unavailable', () => {
        state.allowed = true;
        const host = render(baseRevert({ status: 'reverted', reason: 'already_reverted' }));

        expect((host.querySelector('button') as HTMLElement & { disabled: boolean }).disabled).toBe(true);
        expect(host.textContent).toContain('Already reverted.');
    });

    it('links to the blocking entry', () => {
        state.allowed = true;
        const host = render(
            baseRevert({ status: 'unavailable', reason: 'batch_member_blocked', blocking_activity_id: 42 }),
        );

        expect(host.querySelector('a')?.getAttribute('href')).toBe('/audit-logs/42');
    });

    it('lists conflicting field labels', () => {
        state.allowed = true;
        const host = render(baseRevert({ status: 'unavailable', reason: 'conflict' }), [
            change({ label: 'Phone', conflict: true }),
            change({ field: 'email', label: 'Email' }),
        ]);

        const items = [...host.querySelectorAll('li')].map((li) => li.textContent);
        expect(items).toEqual(['Phone']);
    });

    it('shows the batch hint', () => {
        state.allowed = true;
        expect(render(baseRevert({ batch_size: 3 })).textContent).toContain('Reverts 2 related records');
    });
});
