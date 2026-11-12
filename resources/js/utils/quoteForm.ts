import type { ItemRow } from '@/Components/Invoices/InvoiceItemsEditor.vue';

export type QuoteItemRow = ItemRow & {
    id: string | null;
    frequency: App.Enums.TaskFrequencyEnum | null;
    billing_period: App.Enums.QuoteBillingPeriodEnum;
    note: string | null;
    line_base: number | null;
    line_vat: number | null;
    line_total: number | null;
};

export interface QuoteFormData {
    kind: App.Enums.QuoteKindEnum;
    client_id: string | null;
    cleaning_object_id: string | null;
    subject: string | null;
    number: string | null;
    issue_date: string;
    valid_until: string;
    currency: App.Enums.CurrencyEnum;
    note: string | null;
    included_text: string | null;
    show_totals: boolean;
    show_vat_note: boolean;
    items: QuoteItemRow[];
    customer_name: string | null;
    customer_email: string | null;
    customer_street: string | null;
    customer_city: string | null;
    customer_postal_code: string | null;
    customer_represented_by: string | null;
    customer_ico: string | null;
    customer_dic: string | null;
    document_uuid: string | null;
    document_template_id: string | null;
    document_body: string | null;
    document_show_header: boolean;
    document_show_footer: boolean;
}

export function quotePayload(data: QuoteFormData) {
    const {
        items,
        included_text,
        show_totals,
        show_vat_note,
        document_uuid,
        document_template_id,
        document_body,
        document_show_header,
        document_show_footer,
        ...rest
    } = data;

    return {
        ...rest,
        subject: rest.subject || null,
        number: rest.number || null,
        note: rest.note || null,
        ...(data.kind === 'document'
            ? { document_uuid }
            : {
                  items,
                  included_text: included_text || null,
                  show_totals,
                  show_vat_note,
                  document_template_id,
                  document_body,
                  document_show_header,
                  document_show_footer,
              }),
    };
}
