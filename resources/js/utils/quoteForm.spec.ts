import { describe, expect, it } from 'vitest';
import { quotePayload, type QuoteFormData } from './quoteForm';

function data(overrides: Partial<QuoteFormData> = {}): QuoteFormData {
    return {
        kind: 'itemized',
        client_id: null,
        cleaning_object_id: null,
        subject: '',
        number: '',
        issue_date: '2026-11-11',
        valid_until: '2026-12-11',
        currency: 'EUR',
        note: '',
        included_text: '',
        show_totals: false,
        show_vat_note: true,
        items: [],
        customer_name: 'Acme',
        customer_email: null,
        customer_street: null,
        customer_city: null,
        customer_postal_code: null,
        customer_represented_by: 'Správca s.r.o.',
        customer_ico: '12345678',
        customer_dic: null,
        document_uuid: 'upload-1',
        document_template_id: 'tpl',
        document_body: '<p>x</p>',
        document_show_header: true,
        document_show_footer: false,
        ...overrides,
    };
}

describe('quotePayload', () => {
    it('keeps items, print options and text fields for an itemized quote', () => {
        const payload = quotePayload(data());

        expect(payload).toMatchObject({
            show_totals: false,
            show_vat_note: true,
            document_template_id: 'tpl',
            document_body: '<p>x</p>',
            document_show_footer: false,
            customer_represented_by: 'Správca s.r.o.',
            customer_ico: '12345678',
        });
        expect(payload).not.toHaveProperty('document_uuid');
    });

    it('turns empty subject, number, note and included text into null', () => {
        const payload = quotePayload(data());

        expect(payload).toMatchObject({ subject: null, number: null, note: null, included_text: null });
    });

    it('sends only the upload for a document quote', () => {
        const payload = quotePayload(data({ kind: 'document' }));

        expect(payload).toMatchObject({ document_uuid: 'upload-1' });
        expect(payload).not.toHaveProperty('items');
        expect(payload).not.toHaveProperty('show_totals');
        expect(payload).not.toHaveProperty('document_body');
    });
});
