import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { describeRequestError, postJson } from '@/utils/jsonRequest';

export function useExpenseImportPreview() {
    const { t } = useI18n();
    const preview = ref<App.Data.Expenses.ExpenseImportPreviewData | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    async function load(file: File): Promise<void> {
        const body = new FormData();
        body.append('file', file);

        loading.value = true;
        error.value = null;
        preview.value = null;

        try {
            preview.value = await postJson<App.Data.Expenses.ExpenseImportPreviewData>(
                '/expenses/import/preview',
                body,
            );
        } catch (e) {
            error.value = describeRequestError(e, t);
        } finally {
            loading.value = false;
        }
    }

    return { preview, loading, error, load };
}
