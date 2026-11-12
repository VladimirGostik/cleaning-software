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
    defaultBody: () => string;
}

export function useQuoteTextDocument(options: UseQuoteTextDocumentOptions): {
    isEdited: ComputedRef<boolean>;
    pendingTemplateId: Ref<string | undefined>;
    pickTemplate: (id: string) => void;
    confirmPick: () => void;
    cancelPick: () => void;
} {
    const { form } = options;
    const pendingTemplateId = ref<string | undefined>(undefined);

    const isEdited = computed(() => {
        if (form.document_body === null) return false;

        const current = options.templates().find((template) => template.id === form.document_template_id);

        return form.document_body !== (current?.body ?? options.defaultBody());
    });

    function apply(id: string): void {
        const template = options.templates().find((option) => option.id === id);
        if (!template) return;

        form.document_template_id = template.id;
        form.document_body = template.body;
    }

    function pickTemplate(id: string): void {
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
