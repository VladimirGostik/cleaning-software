import { computed, ref, type ComputedRef, type Ref } from 'vue';

export interface QuoteTextDocumentFields {
    document_template_id: string | null;
    document_body: string | null;
    document_show_header: boolean;
    document_show_footer: boolean;
}

interface UseQuoteTextDocumentOptions {
    form: QuoteTextDocumentFields;
    templates: () => readonly App.Data.Quotes.QuoteTemplateOptionData[];
}

export function useQuoteTextDocument(options: UseQuoteTextDocumentOptions): {
    isEdited: ComputedRef<boolean>;
    pendingTemplateId: Ref<string | null | undefined>;
    pickTemplate: (id: string | null) => void;
    confirmPick: () => void;
    cancelPick: () => void;
} {
    const { form } = options;
    const pendingTemplateId = ref<string | null | undefined>(undefined);

    const isEdited = computed(() => {
        if (form.document_body === null) return false;

        const current = options.templates().find((template) => template.id === form.document_template_id);

        return current ? form.document_body !== current.body : true;
    });

    function apply(id: string | null): void {
        const template = id === null ? null : (options.templates().find((option) => option.id === id) ?? null);

        form.document_template_id = template?.id ?? null;
        form.document_body = template?.body ?? null;
    }

    function pickTemplate(id: string | null): void {
        if (id === form.document_template_id) return;

        if (isEdited.value) {
            pendingTemplateId.value = id;
            return;
        }

        apply(id);
    }

    function confirmPick(): void {
        if (pendingTemplateId.value !== undefined) apply(pendingTemplateId.value);
        pendingTemplateId.value = undefined;
    }

    function cancelPick(): void {
        pendingTemplateId.value = undefined;
    }

    return { isEdited, pendingTemplateId, pickTemplate, confirmPick, cancelPick };
}
