import { useI18n } from 'vue-i18n';
import { useDeleteConfirm } from '@/Composables/useDeleteConfirm';
import { revertConfirmKey, revertSubjectLabel } from '@/utils/auditLog';

export function useActivityRevertConfirm(): ReturnType<typeof useDeleteConfirm<App.Data.ActivityLogListItemData>> {
    const { t } = useI18n();

    return useDeleteConfirm<App.Data.ActivityLogListItemData>({
        method: 'post',
        resolveUrl: (activity) => `/audit-logs/${activity.id}/revert`,
        getTitle: () => t('audit_revert_confirm_title'),
        getDescription: (activity) => {
            const { changes_count, batch_size } = activity.revert;
            const base = t(
                revertConfirmKey(activity.event),
                { subject: revertSubjectLabel(activity), count: changes_count },
                changes_count,
            );
            if (batch_size <= 1) return base;
            return `${base} ${t('audit_revert_confirm_batch', { count: batch_size - 1 }, batch_size - 1)}`;
        },
    });
}
