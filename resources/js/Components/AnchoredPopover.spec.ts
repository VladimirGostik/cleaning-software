import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, ref } from 'vue';
import type { App } from 'vue';

import AnchoredPopover from './AnchoredPopover.vue';
import DateInput from './Forms/DateInput.vue';

vi.mock('vue-i18n', () => ({
    useI18n: () => ({ t: (key: string) => key, locale: { value: 'sk' } }),
}));

let app: App | null = null;

function mountPopover() {
    const open = ref(false);
    const parentKeydown = vi.fn();
    const host = document.createElement('div');
    host.addEventListener('keydown', parentKeydown);
    document.body.appendChild(host);
    app = createApp({
        render: () =>
            h(
                AnchoredPopover,
                { open: open.value, 'onUpdate:open': (v: boolean) => (open.value = v) },
                {
                    trigger: ({ toggle }: { toggle: () => void }) =>
                        h('button', { id: 'trigger', onClick: toggle }, 'open'),
                    default: () => [h('button', { id: 'first' }, 'a'), h('button', { id: 'last' }, 'b')],
                },
            ),
    });
    app.mount(host);

    return { host, open, parentKeydown };
}

function focusOut(from: Element, to: Element): void {
    const event = new Event('focusout', { bubbles: true });
    Object.defineProperty(event, 'relatedTarget', { value: to });
    from.dispatchEvent(event);
}

async function openIt(host: HTMLElement) {
    host.querySelector<HTMLElement>('#trigger')!.click();
    await nextTick();
    await nextTick();
}

describe('AnchoredPopover', () => {
    afterEach(() => {
        app?.unmount();
        app = null;
        document.body.innerHTML = '';
    });

    it('stacks the panel with the popover z token, above the DaisyUI modal', async () => {
        const { host } = mountPopover();
        await openIt(host);

        const panel = document.body.querySelector('.kg-float')!;
        expect(panel.classList.contains('z-(--kg-z-popover)')).toBe(true);
        expect(panel.classList.contains('z-50')).toBe(false);
    });

    it('closes on Escape without the key reaching a parent listener', async () => {
        const { host, open, parentKeydown } = mountPopover();
        await openIt(host);

        document.body
            .querySelector('#first')!
            .dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await nextTick();

        expect(open.value).toBe(false);
        expect(parentKeydown).not.toHaveBeenCalled();
    });

    it('closes and returns focus to the trigger on Tab past the last control', async () => {
        const { host, open } = mountPopover();
        await openIt(host);

        const last = document.body.querySelector<HTMLElement>('#last')!;
        last.focus();
        last.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
        await nextTick();
        await nextTick();

        expect(open.value).toBe(false);
        expect(document.activeElement).toBe(host.querySelector('#trigger'));
    });

    it('closes when focus moves to an element outside the popover', async () => {
        const { host, open } = mountPopover();
        await openIt(host);
        const outside = document.createElement('button');
        document.body.appendChild(outside);

        const first = document.body.querySelector<HTMLElement>('#first')!;
        focusOut(first, outside);
        await nextTick();

        expect(open.value).toBe(false);
    });

    it('stays open when focus moves inside the popover', async () => {
        const { host, open } = mountPopover();
        await openIt(host);

        const first = document.body.querySelector<HTMLElement>('#first')!;
        const last = document.body.querySelector<HTMLElement>('#last')!;
        focusOut(first, last);
        await nextTick();

        expect(open.value).toBe(true);
    });

    describe('with a DateInput nested in a popover', () => {
        function mountNested() {
            const open = ref(false);
            const picked: Array<string | null> = [];
            const model = ref<string | null>('2026-11-13');
            const host = document.createElement('div');
            document.body.appendChild(host);
            app = createApp({
                render: () =>
                    h(
                        AnchoredPopover,
                        { open: open.value, 'onUpdate:open': (v: boolean) => (open.value = v) },
                        {
                            trigger: ({ toggle }: { toggle: () => void }) =>
                                h('button', { id: 'outer', onClick: toggle }, 'open'),
                            default: () =>
                                h(DateInput, {
                                    label: 'Dátum',
                                    modelValue: model.value,
                                    'onUpdate:modelValue': (v: string | null) => {
                                        picked.push(v);
                                        model.value = v;
                                    },
                                }),
                        },
                    ),
            });
            app.mount(host);

            return { host, open, picked };
        }

        async function openBoth(host: HTMLElement) {
            host.querySelector<HTMLElement>('#outer')!.click();
            await nextTick();
            await nextTick();
            document.body.querySelector<HTMLElement>('button[aria-label^="date_picker_open"]')!.click();
            await nextTick();
            await nextTick();
        }

        it('keeps the outer popover open when a day in the inner panel is pressed and clicked', async () => {
            const { host, open, picked } = mountNested();
            await openBoth(host);

            const day = document.body.querySelector<HTMLElement>('[data-day="2026-11-20"]')!;
            day.dispatchEvent(new Event('pointerdown', { bubbles: true }));
            await nextTick();
            expect(open.value).toBe(true);

            day.click();
            await nextTick();

            expect(picked).toEqual(['2026-11-20']);
            expect(open.value).toBe(true);
        });

        it('keeps the outer popover open when focus moves into the inner panel', async () => {
            const { host, open } = mountNested();
            await openBoth(host);

            const input = document.body.querySelector<HTMLElement>('input[type="text"]')!;
            focusOut(input, document.body.querySelector<HTMLElement>('[data-day="2026-11-20"]')!);
            await nextTick();

            expect(open.value).toBe(true);
        });

        it('closes only the inner popover on Escape', async () => {
            const { host, open } = mountNested();
            await openBoth(host);

            document.body
                .querySelector('[data-day="2026-11-13"]')!
                .dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            await nextTick();
            await nextTick();

            expect(document.body.querySelector('[role="grid"]')).toBeNull();
            expect(open.value).toBe(true);
        });
    });
});
