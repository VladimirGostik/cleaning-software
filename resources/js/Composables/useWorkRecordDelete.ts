import { useI18n } from 'vue-i18n';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';

export function useWorkRecordDelete() {
    const { t } = useI18n();

    return useDeleteConfirm<App.Data.Schedule.JobWorkRecordData>({
        resolveUrl: (record) => `/jobs/${record.job_id}/work-record`,
        getTitle: () => t('work_record_delete'),
        getDescription: () => t('work_record_delete_confirm'),
    });
}
