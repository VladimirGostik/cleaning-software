import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { describeRequestError, postJson } from '@/utils/jsonRequest';

export function useExpenseAnalyze() {
    const { t } = useI18n();
    const draft = ref<App.Data.Expenses.ExpenseDraftData | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    async function analyze(input: {
        file?: File | null;
        text?: string;
    }): Promise<App.Data.Expenses.ExpenseDraftData | null> {
        const body = new FormData();
        if (input.file) {
            body.append('receipt', input.file);
        } else if (input.text) {
            body.append('text', input.text);
        } else {
            return null;
        }

        loading.value = true;
        error.value = null;

        try {
            draft.value = await postJson<App.Data.Expenses.ExpenseDraftData>('/expenses/analyze', body);

            return draft.value;
        } catch (e) {
            error.value = describeRequestError(e, t);

            return null;
        } finally {
            loading.value = false;
        }
    }

    return { draft, loading, error, analyze };
}
