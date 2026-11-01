import { createI18n } from 'vue-i18n';
import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import EmployeeAccessBadge from './EmployeeAccessBadge.vue';

import skApp from '../../../lang/sk/app.json';

function render(props: { hasAccess: boolean; invitationPending: boolean }): HTMLElement {
    const host = document.createElement('div');
    const i18n = createI18n({ legacy: false, locale: 'sk', messages: { sk: skApp } });
    createApp(EmployeeAccessBadge, props).use(i18n).mount(host);
    return host.firstElementChild as HTMLElement;
}

describe('EmployeeAccessBadge', () => {
    it('shows neutral "no access" without a login', () => {
        const el = render({ hasAccess: false, invitationPending: false });
        expect(el.classList.contains('kg-chip--neutral')).toBe(true);
        expect(el.textContent?.trim()).toBe('Bez prístupu');
    });

    it('shows warning while the invitation is pending', () => {
        const el = render({ hasAccess: true, invitationPending: true });
        expect(el.classList.contains('kg-chip--warning')).toBe(true);
        expect(el.textContent?.trim()).toBe('Čaká na pozvánku');
    });

    it('shows done for an active login', () => {
        const el = render({ hasAccess: true, invitationPending: false });
        expect(el.classList.contains('kg-chip--done')).toBe(true);
        expect(el.textContent?.trim()).toBe('Má prístup');
    });
});
