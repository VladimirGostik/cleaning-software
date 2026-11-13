import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';

import { useWorkRecordDelete } from './useWorkRecordDelete';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@inertiajs/vue3', () => ({ router: { delete: vi.fn(), post: vi.fn() } }));

const record = { job_id: 'j1' } as App.Data.Schedule.JobWorkRecordData;

beforeEach(() => {
    vi.mocked(router.delete).mockReset();
});

describe('useWorkRecordDelete', () => {
    it('labels the confirm with the work-record keys once a record is chosen', () => {
        const deletion = useWorkRecordDelete();

        expect(deletion.getModalTitle()).toBe('');

        deletion.openModal(record);

        expect(deletion.state.isOpen).toBe(true);
        expect(deletion.getModalTitle()).toBe('work_record_delete');
        expect(deletion.getModalDescription()).toBe('work_record_delete_confirm');
    });

    it('sends DELETE to the job work-record route on confirm', () => {
        const deletion = useWorkRecordDelete();

        deletion.openModal(record);
        deletion.confirmDelete();

        expect(router.delete).toHaveBeenCalledWith(
            '/jobs/j1/work-record',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('sends nothing after cancel', () => {
        const deletion = useWorkRecordDelete();

        deletion.openModal(record);
        deletion.closeModal();
        deletion.confirmDelete();

        expect(router.delete).not.toHaveBeenCalled();
    });
});
