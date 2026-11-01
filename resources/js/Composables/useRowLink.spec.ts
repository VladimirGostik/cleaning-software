import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const visit = vi.fn();
vi.mock('@inertiajs/vue3', () => ({ router: { visit: (...args: unknown[]) => visit(...args) } }));

import { useRowLink } from './useRowLink';

function selection(text: string): ReturnType<typeof window.getSelection> {
    return { toString: () => text } as unknown as ReturnType<typeof window.getSelection>;
}

function eventOn(tag: string, init: ConstructorParameters<typeof MouseEvent>[1] = {}): MouseEvent {
    const row = document.createElement('tr');
    const target = document.createElement(tag);
    row.appendChild(target);
    document.body.appendChild(row);
    const event = new MouseEvent('click', { bubbles: true, ...init });
    Object.defineProperty(event, 'target', { value: target });
    return event;
}

describe('useRowLink', () => {
    const { onClick, onAuxClick, rowClass } = useRowLink();
    let open: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        visit.mockClear();
        open = vi.spyOn(window, 'open').mockImplementation(() => null);
        vi.spyOn(window, 'getSelection').mockReturnValue(selection(''));
    });

    afterEach(() => {
        vi.restoreAllMocks();
        document.body.innerHTML = '';
    });

    it('visits the href on a plain click', () => {
        onClick('/a', eventOn('span'));
        expect(visit).toHaveBeenCalledWith('/a');
    });

    it('runs the custom open handler instead of visiting', () => {
        const custom = vi.fn();
        onClick('/a', eventOn('span'), custom);
        expect(custom).toHaveBeenCalledOnce();
        expect(visit).not.toHaveBeenCalled();
    });

    it('does nothing for a null href', () => {
        onClick(null, eventOn('span'));
        onAuxClick(null, eventOn('span', { button: 1 }));
        expect(visit).not.toHaveBeenCalled();
        expect(open).not.toHaveBeenCalled();
        expect(rowClass(null)).toBe('');
        expect(rowClass('/a')).toBe('cursor-pointer');
    });

    it.each(['a', 'button', 'input', 'select', 'textarea', 'label'])('ignores a click on <%s>', (tag) => {
        onClick('/a', eventOn(tag));
        onAuxClick('/a', eventOn(tag, { button: 1 }));
        expect(visit).not.toHaveBeenCalled();
        expect(open).not.toHaveBeenCalled();
    });

    it('ignores a click inside role=button', () => {
        const wrapper = document.createElement('div');
        wrapper.setAttribute('role', 'button');
        const inner = document.createElement('span');
        wrapper.appendChild(inner);
        const event = new MouseEvent('click', { bubbles: true });
        Object.defineProperty(event, 'target', { value: inner });
        onClick('/a', event);
        expect(visit).not.toHaveBeenCalled();
    });

    it('ignores a click while text is selected', () => {
        vi.spyOn(window, 'getSelection').mockReturnValue(selection('abc'));
        onClick('/a', eventOn('span'));
        onAuxClick('/a', eventOn('span', { button: 1 }));
        expect(visit).not.toHaveBeenCalled();
        expect(open).not.toHaveBeenCalled();
    });

    it.each([{ ctrlKey: true }, { metaKey: true }])('opens a new tab on %o', (modifier) => {
        onClick('/a', eventOn('span', modifier));
        expect(open).toHaveBeenCalledWith('/a', '_blank', 'noopener');
        expect(visit).not.toHaveBeenCalled();
    });

    it('opens a new tab on middle click only', () => {
        onAuxClick('/a', eventOn('span', { button: 1 }));
        expect(open).toHaveBeenCalledWith('/a', '_blank', 'noopener');
        open.mockClear();
        onAuxClick('/a', eventOn('span', { button: 2 }));
        expect(open).not.toHaveBeenCalled();
    });
});
