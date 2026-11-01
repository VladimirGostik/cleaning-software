import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { describeRequestError, postJson } from '@/utils/jsonRequest';

export type ImportRow = {
    -readonly [K in keyof App.Data.Expenses.ExpenseImportRowData]: App.Data.Expenses.ExpenseImportRowData[K];
};

const ROW_STATES: readonly App.Enums.ExpenseImportRowStateEnum[] = [
    'new',
    'already_imported',
    'previously_deleted',
    'possible_match',
];

export function useExpenseImport() {
    const { t } = useI18n();
    const preview = ref<App.Data.Expenses.ExpenseImportPreviewData | null>(null);
    const rows = ref<ImportRow[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const submitting = ref(false);
    const submitErrors = ref<string[]>([]);

    const counts = computed(() => {
        const result = Object.fromEntries(ROW_STATES.map((state) => [state, 0])) as Record<
            App.Enums.ExpenseImportRowStateEnum,
            number
        >;
        for (const row of rows.value) result[row.state]++;

        return result;
    });
    const selectedCount = computed(() => rows.value.filter((row) => row.include).length);
    const totalMovements = computed(
        () => rows.value.length + (preview.value?.skipped_credit_rows ?? 0) + (preview.value?.skipped_other_rows ?? 0),
    );
    const allAlreadyImported = computed(
        () => rows.value.length > 0 && rows.value.every((row) => row.state === 'already_imported'),
    );

    function clear(): void {
        preview.value = null;
        rows.value = [];
        error.value = null;
        submitErrors.value = [];
    }

    async function load(file: File): Promise<void> {
        const body = new FormData();
        body.append('file', file);

        clear();
        loading.value = true;

        try {
            const response = await postJson<App.Data.Expenses.ExpenseImportPreviewData>(
                '/expenses/import/preview',
                body,
            );
            preview.value = response;
            rows.value = response.rows.map((row) => ({ ...row }));
        } catch (e) {
            error.value = describeRequestError(e, t);
        } finally {
            loading.value = false;
        }
    }

    function find(reference: string): ImportRow | undefined {
        return rows.value.find((row) => row.reference === reference);
    }

    function setInclude(reference: string, include: boolean): void {
        const row = find(reference);
        if (!row || row.state === 'already_imported') return;
        row.include = include;
    }

    function setCategory(reference: string, category: App.Enums.ExpenseCategoryEnum | null): void {
        const row = find(reference);
        if (row) row.proposed_category = category;
    }

    function submit(): void {
        if (!preview.value || submitting.value) return;

        const payload: App.Data.Expenses.ExpenseImportData = {
            account_iban: preview.value.account_check.statement_iban,
            rows: rows.value.map((row) => ({
                reference: row.reference,
                date: row.date,
                amount: row.amount,
                counterparty: row.counterparty,
                note: row.note,
                proposed_category: row.proposed_category,
                confidence: row.confidence,
                include: row.include,
            })),
        };

        router.post('/expenses/import', payload, {
            preserveState: 'errors',
            preserveScroll: 'errors',
            onStart: () => {
                submitting.value = true;
                submitErrors.value = [];
            },
            onFinish: () => {
                submitting.value = false;
            },
            onError: (errors: Record<string, string>) => {
                submitErrors.value = [...new Set(Object.values(errors))];
            },
        });
    }

    return {
        preview,
        rows,
        loading,
        error,
        submitting,
        submitErrors,
        counts,
        selectedCount,
        totalMovements,
        allAlreadyImported,
        load,
        clear,
        setInclude,
        setCategory,
        submit,
    };
}
