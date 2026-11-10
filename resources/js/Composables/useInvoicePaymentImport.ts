import { computed, ref, shallowRef } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { describeRequestError, postJson } from '@/utils/jsonRequest';
import {
    FILTER_OF_STATE,
    PAYMENT_IMPORT_FILTERS,
    canAssign,
    canInclude,
    matchesSearch,
    projectRows,
    rowInputs,
    sumAmounts,
    toRow,
    validateFiles,
    type PaymentImportFilter,
    type PaymentImportRow,
} from '@/utils/invoicePaymentImport';

export function useInvoicePaymentImport(limits: () => App.Data.Invoices.PaymentImportLimitsData) {
    const { t } = useI18n();
    const files = shallowRef<File[]>([]);
    const preview = ref<App.Data.Invoices.PaymentImportPreviewData | null>(null);
    const rows = ref<PaymentImportRow[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const submitting = ref(false);
    const submitErrors = ref<string[]>([]);
    const filter = ref<PaymentImportFilter>('review');
    const search = ref('');
    const defaults = new Map<string, boolean>();

    const projections = computed(() => projectRows(rows.value));
    const visibleRows = computed(() =>
        rows.value.filter(
            (row) =>
                (filter.value === 'all' || FILTER_OF_STATE[row.state] === filter.value) &&
                matchesSearch(row, search.value),
        ),
    );
    const totals = computed(() => {
        const result = Object.fromEntries(
            PAYMENT_IMPORT_FILTERS.map((key) => {
                const group = rows.value.filter((row) => key === 'all' || FILTER_OF_STATE[row.state] === key);

                return [key, { count: group.length, amount: sumAmounts(group.map((row) => row.amount)) }];
            }),
        ) as Record<PaymentImportFilter, { count: number; amount: string }>;

        return result;
    });
    const selection = computed(() => {
        const included = rows.value.filter(
            (row) => row.include && projections.value.get(row.reference)?.outcome !== 'nothing',
        );

        return {
            count: included.length,
            amount: sumAmounts(included.map((row) => projections.value.get(row.reference)?.amount_to_record ?? '0')),
        };
    });
    const hasMatchedFile = computed(
        () => preview.value?.files.some((file) => file.account.status === 'matched') ?? false,
    );

    function clear(): void {
        files.value = [];
        preview.value = null;
        rows.value = [];
        error.value = null;
        submitErrors.value = [];
        search.value = '';
        filter.value = 'review';
        defaults.clear();
    }

    async function load(selected: File[]): Promise<void> {
        clear();

        const invalid = validateFiles(selected, limits());
        if (invalid) {
            error.value = t(invalid.key, invalid.params);

            return;
        }

        const body = new FormData();
        selected.forEach((file) => body.append('files[]', file));
        loading.value = true;

        try {
            const response = await postJson<App.Data.Invoices.PaymentImportPreviewData>(
                '/invoices/payment-import/preview',
                body,
            );
            files.value = selected;
            preview.value = response;
            rows.value = response.rows.map(toRow);
            rows.value.forEach((row) => defaults.set(row.reference, row.include));
            filter.value = rows.value.some((row) => FILTER_OF_STATE[row.state] === 'review') ? 'review' : 'all';
        } catch (e) {
            error.value = describeRequestError(e, t);
        } finally {
            loading.value = false;
        }
    }

    function find(reference: string): PaymentImportRow | undefined {
        return rows.value.find((row) => row.reference === reference);
    }

    function setInclude(reference: string, include: boolean): void {
        const row = find(reference);
        if (!row || (include && !canInclude(row))) return;
        if (row.state === 'already_imported') return;
        row.include = include;
    }

    function setIncludeVisible(include: boolean): void {
        for (const row of visibleRows.value) {
            if (row.state === 'already_imported') continue;
            if (include ? canInclude(row) : true) row.include = include;
        }
    }

    function assign(reference: string, invoice: App.Data.Invoices.PaymentImportInvoiceData): void {
        const row = find(reference);
        if (!row || !canAssign(row)) return;
        row.manual_invoice = invoice;
        row.include = true;
    }

    function unassign(reference: string): void {
        const row = find(reference);
        if (!row) return;
        row.manual_invoice = null;
        row.include = (defaults.get(reference) ?? false) && canInclude(row);
    }

    function submit(): void {
        if (!preview.value || submitting.value) return;

        router.post(
            '/invoices/payment-import',
            { files: files.value, rows: rowInputs(rows.value) },
            {
                forceFormData: true,
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
            },
        );
    }

    return {
        files,
        preview,
        rows,
        loading,
        error,
        submitting,
        submitErrors,
        filter,
        search,
        projections,
        visibleRows,
        totals,
        selection,
        hasMatchedFile,
        load,
        clear,
        setInclude,
        setIncludeVisible,
        assign,
        unassign,
        submit,
    };
}
