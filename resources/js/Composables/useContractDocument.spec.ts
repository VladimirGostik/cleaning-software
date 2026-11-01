import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';

import { HttpError, postJson } from '@/utils/http';
import { useContractDocument, type ContractFormData, type ContractFormInstance } from './useContractDocument';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('@/utils/http', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/http')>()),
    postJson: vi.fn(),
}));

const tokens: App.Data.DocumentTemplates.DocumentTokenData[] = [
    { key: 'client.dic', label: 'DIC klienta', kind: 'optional', group: 'client' },
];

function makeForm(overrides: Partial<ContractFormData> = {}) {
    const state = reactive({
        title: 'Zmluva',
        number: null,
        category: 'service_agreement',
        term_type: 'indefinite',
        contractable_type: 'client',
        contractable_id: 'c1',
        document_template_id: 't1',
        body: '',
        show_header: true,
        show_footer: true,
        valid_from: '2026-11-01',
        end_date: null,
        notes: null,
        employment: null,
        ...overrides,
        setError: vi.fn(),
        clearErrors: vi.fn(),
    });

    return state as unknown as ContractFormInstance & { setError: ReturnType<typeof vi.fn> };
}

const postJsonMock = vi.mocked(postJson);

beforeEach(() => postJsonMock.mockReset());

describe('useContractDocument', () => {
    it('fills the body from the generate response and clears the stale flag', async () => {
        postJsonMock.mockResolvedValue({ html: '<p>Text {{client.dic}}</p>', missing_tokens: [], unknown_tokens: [] });
        const form = makeForm();
        const doc = useContractDocument({ form, tokens: () => tokens, initialBody: null });

        expect(doc.isStale.value).toBe(true);
        expect(await doc.generate()).toBe(true);

        expect(form.body).toBe('<p>Text {{client.dic}}</p>');
        expect(doc.isStale.value).toBe(false);
        expect(doc.isEdited.value).toBe(false);
        expect(doc.missing.value).toEqual([{ key: 'client.dic', label: 'DIC klienta' }]);
    });

    it('marks the text edited and the inputs stale independently', async () => {
        postJsonMock.mockResolvedValue({ html: '<p>x</p>', missing_tokens: [], unknown_tokens: [] });
        const form = makeForm();
        const doc = useContractDocument({ form, tokens: () => tokens, initialBody: null });
        await doc.generate();

        form.body = '<p>x edited</p>';
        expect(doc.isEdited.value).toBe(true);
        expect(doc.isStale.value).toBe(false);

        form.contractable_id = 'c2';
        expect(doc.isStale.value).toBe(true);
    });

    it('treats a loaded body as edited and fresh', () => {
        const form = makeForm({ body: '<p>saved</p>' });
        const doc = useContractDocument({ form, tokens: () => tokens, initialBody: '<p>saved</p>' });

        expect(doc.isEdited.value).toBe(true);
        expect(doc.isStale.value).toBe(false);
    });

    it('falls back to the key when a missing token has no catalogue label', () => {
        const form = makeForm({ body: '<p>{{unknown.key}}</p>' });
        const doc = useContractDocument({ form, tokens: () => tokens, initialBody: '<p>{{unknown.key}}</p>' });

        expect(doc.missing.value).toEqual([{ key: 'unknown.key', label: 'unknown.key' }]);
    });

    it('maps a 422 onto form errors and keeps the body', async () => {
        postJsonMock.mockRejectedValueOnce(new HttpError(422, null, null, { contractable_id: ['Povinne'] }));
        const form = makeForm();
        const doc = useContractDocument({ form, tokens: () => tokens, initialBody: null });

        expect(await doc.generate()).toBe(false);
        expect(form.setError).toHaveBeenCalledWith({ contractable_id: 'Povinne' });
        expect(form.body).toBe('');
        expect(doc.generateError.value).toBeNull();
    });

    it('reports throttling and generic failures', async () => {
        const form = makeForm();
        const doc = useContractDocument({ form, tokens: () => tokens, initialBody: null });

        postJsonMock.mockRejectedValueOnce(new HttpError(429, 30));
        await doc.generate();
        expect(doc.generateError.value).toBe('document_generate_throttled');

        postJsonMock.mockRejectedValueOnce(new HttpError(500));
        await doc.generate();
        expect(doc.generateError.value).toBe('document_generate_failed');
    });

    it('sends the template for generation and the edited text for the pdf', () => {
        const form = makeForm({ body: '<p>edited</p>' });
        const doc = useContractDocument({ form, tokens: () => tokens, initialBody: null });

        expect(doc.generatePayload()).toMatchObject({ document_template_id: 't1', body: null });
        expect(doc.pdfPayload()).toMatchObject({ document_template_id: null, body: '<p>edited</p>' });
    });
});
