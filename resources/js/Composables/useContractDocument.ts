import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { InertiaForm } from '@inertiajs/vue3';

import type { ContractBasicsFormData } from '@/Components/Contracts/ContractBasicsFields.vue';
import type { EmploymentFormData } from '@/Components/Contracts/EmploymentContractFields.vue';
import { usedTokenKeys } from '@/Components/Documents/editor/extensions/templateTokenHtml';
import { HttpError, postJson } from '@/utils/http';

export interface ContractFormData extends ContractBasicsFormData {
    category: App.Enums.ContractCategoryEnum;
    contractable_type: App.Enums.ContractableTypeEnum;
    contractable_id: string;
    document_template_id: string | null;
    body: string;
    show_header: boolean;
    show_footer: boolean;
    notes: string | null;
    employment: EmploymentFormData | null;
}

export type ContractFormInstance = InertiaForm<ContractFormData>;

type DocumentTokenDef = App.Data.DocumentTemplates.DocumentTokenData;

export const GENERATE_URL = '/contracts/generate';
export const GENERATE_PDF_URL = '/contracts/generate-pdf';

export function useContractDocument(options: {
    form: ContractFormInstance;
    tokens: () => readonly DocumentTokenDef[];
    initialBody: string | null;
}) {
    const { form } = options;
    const { t } = useI18n();

    const generating = ref(false);
    const generateError = ref<string | null>(null);

    const lastGeneratedBody = ref<string | null>(options.initialBody === null ? '' : null);
    const lastGeneratedSignature = ref<string | null>(options.initialBody === null ? null : signature());

    function signature(): string {
        return JSON.stringify([
            form.category,
            form.term_type,
            form.contractable_type,
            form.contractable_id,
            form.valid_from,
            form.term_type === 'fixed' ? form.end_date : null,
            form.document_template_id || null,
            form.title,
            form.number || null,
            form.category === 'employment' ? form.employment : null,
        ]);
    }

    const isEdited = computed(() =>
        lastGeneratedBody.value === null ? form.body.trim() !== '' : form.body !== lastGeneratedBody.value,
    );

    const isStale = computed(() => signature() !== lastGeneratedSignature.value);

    const missing = computed(() => {
        const labels = new Map(options.tokens().map((def) => [def.key, def.label]));

        return usedTokenKeys(form.body).map((key) => ({ key, label: labels.get(key) ?? key }));
    });

    function generatePayload(): App.Data.Contracts.ContractGenerateData {
        return {
            document_template_id: form.document_template_id || null,
            body: null,
            title: form.title.trim() === '' ? null : form.title,
            number: form.number || null,
            category: form.category,
            term_type: form.term_type,
            contractable_type: form.contractable_type,
            contractable_id: form.contractable_id,
            valid_from: form.valid_from,
            end_date: form.term_type === 'fixed' ? form.end_date : null,
            employment: form.category === 'employment' ? form.employment : null,
            quote_id: null,
            show_header: form.show_header,
            show_footer: form.show_footer,
        };
    }

    function pdfPayload(): App.Data.Contracts.ContractGenerateData {
        return { ...generatePayload(), document_template_id: null, body: form.body };
    }

    function reportFailure(err: unknown): void {
        if (err instanceof HttpError && err.status === 422 && err.errors !== null) {
            const flat: Record<string, string> = {};
            Object.entries(err.errors).forEach(([field, messages]) => {
                if (messages[0] !== undefined) flat[field] = messages[0];
            });
            form.setError(flat as Parameters<typeof form.setError>[0]);
            return;
        }

        if (err instanceof HttpError && err.status === 429) {
            generateError.value = t('document_generate_throttled');
            return;
        }

        generateError.value = (err instanceof HttpError ? err.serverMessage : null) ?? t('document_generate_failed');
    }

    async function generate(): Promise<boolean> {
        generating.value = true;
        generateError.value = null;
        form.clearErrors();
        const sentSignature = signature();

        try {
            const result = await postJson<App.Data.Documents.DocumentRenderData>(GENERATE_URL, generatePayload());
            form.body = result.html;
            lastGeneratedBody.value = result.html;
            lastGeneratedSignature.value = sentSignature;

            return true;
        } catch (err) {
            reportFailure(err);

            return false;
        } finally {
            generating.value = false;
        }
    }

    return { generating, generateError, isEdited, isStale, missing, generate, generatePayload, pdfPayload };
}
