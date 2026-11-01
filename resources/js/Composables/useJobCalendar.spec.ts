import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useJobCalendar } from './useJobCalendar';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('vue', async (importOriginal) => ({
    ...(await importOriginal<typeof import('vue')>()),
    onBeforeUnmount: vi.fn(),
}));

const range = { from: '2026-11-01', to: '2026-12-01' };
const otherRange = { from: '2026-12-01', to: '2027-01-01' };

describe('useJobCalendar', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        fetchMock.mockReset();
        fetchMock.mockImplementation(async () => ({ ok: true, json: async () => [] }));
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('sends no request for the same range twice', async () => {
        const { loadIfChanged } = useJobCalendar();
        await loadIfChanged(range);
        await loadIfChanged({ ...range });
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('sends a request for a new range', async () => {
        const { loadIfChanged } = useJobCalendar();
        await loadIfChanged(range);
        await loadIfChanged(otherRange);
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('reload always sends a request for the current range', async () => {
        const { loadIfChanged, reload } = useJobCalendar();
        await loadIfChanged(range);
        await reload();
        await reload();
        expect(fetchMock).toHaveBeenCalledTimes(3);
    });
});
