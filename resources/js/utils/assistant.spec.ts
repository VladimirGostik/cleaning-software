import { describe, expect, it } from 'vitest';
import { HttpError } from '@/utils/http';
import {
    MAX_MESSAGES,
    MAX_MESSAGE_CHARS,
    MAX_TOTAL_CHARS,
    ASSISTANT_ATTACHMENTS_MAX_TOTAL_BYTES,
    ASSISTANT_IMAGE_MAX_BYTES,
    ASSISTANT_PDF_MAX_BYTES,
    assistantAttachmentRejection,
    buildAssistantFormData,
    buildPayloadMessages,
    describeAssistantError,
    isAllowedAssistantLink,
    isAttachmentRejection,
} from './assistant';

const UUID = '0197a1c2-3b4d-7e5f-8a6b-7c8d9e0f1a2b';

function turn(content: string, role: App.Enums.AssistantRoleEnum = 'user') {
    return { role, content };
}

describe('buildPayloadMessages', () => {
    it('drops empty turns', () => {
        expect(buildPayloadMessages([turn('hi'), turn('   '), turn('there')])).toEqual([turn('hi'), turn('there')]);
    });

    it('keeps the newest messages up to the count limit', () => {
        const history = Array.from({ length: MAX_MESSAGES + 1 }, (_, i) => turn(`m${i}`));
        const result = buildPayloadMessages(history);

        expect(result).toHaveLength(MAX_MESSAGES);
        expect(result[0].content).toBe('m1');
    });

    it('cuts a single message at the character limit', () => {
        expect(buildPayloadMessages([turn('x'.repeat(MAX_MESSAGE_CHARS + 1))])[0].content).toHaveLength(
            MAX_MESSAGE_CHARS,
        );
    });

    it('drops the oldest turns until the total fits', () => {
        const big = 'x'.repeat(MAX_MESSAGE_CHARS);
        const result = buildPayloadMessages([turn(big), turn(big), turn(big), turn('last')]);

        expect(result.reduce((sum, m) => sum + m.content.length, 0)).toBeLessThanOrEqual(MAX_TOTAL_CHARS);
        expect(result.at(-1)?.content).toBe('last');
    });
});

describe('describeAssistantError', () => {
    it('reads the ai text from a 422', () => {
        const error = describeAssistantError(new HttpError(422, null, null, { ai: ['Not available'] }));

        expect(error).toMatchObject({ kind: 'ai', message: 'Not available' });
    });

    it('reports other 422 errors as validation', () => {
        const error = describeAssistantError(new HttpError(422, null, null, { messages: ['Too long'] }));

        expect(error).toMatchObject({ kind: 'validation', message: 'Too long' });
    });

    it('maps 429 to rate', () => {
        expect(describeAssistantError(new HttpError(429, 12)).kind).toBe('rate');
    });

    it('maps 403 with the server message', () => {
        expect(describeAssistantError(new HttpError(403, null, 'No'))).toMatchObject({
            kind: 'forbidden',
            message: 'No',
        });
    });

    it.each([401, 419])('maps %i to session', (status) => {
        expect(describeAssistantError(new HttpError(status)).kind).toBe('session');
    });

    it('treats 5xx, network and abort failures as outcome unknown', () => {
        const kinds = [
            new HttpError(502),
            new TypeError('Failed to fetch'),
            new DOMException('aborted', 'AbortError'),
            new DOMException('timeout', 'TimeoutError'),
        ].map((e) => describeAssistantError(e).kind);

        expect(kinds).toEqual(Array(4).fill('outcome_unknown'));
    });

    it('falls back to other', () => {
        expect(describeAssistantError(new Error('x')).kind).toBe('other');
        expect(describeAssistantError(new HttpError(404)).kind).toBe('other');
    });
});

describe('isAllowedAssistantLink', () => {
    it.each(['tasks', 'invoices', 'quotes', 'expenses'])('accepts %s detail links', (resource) => {
        expect(isAllowedAssistantLink(`/${resource}/${UUID}`)).toBe(true);
    });

    it('accepts the pending actions page', () => {
        expect(isAllowedAssistantLink('/pending-actions')).toBe(true);
    });

    it.each([
        null,
        '/settings',
        '/tasks',
        `/tasks/${UUID}/edit`,
        `https://evil.example/tasks/${UUID}`,
        `//evil.example/tasks/${UUID}`,
        'javascript:alert(1)',
        '/tasks/not-a-uuid',
    ])('rejects %s', (url) => {
        expect(isAllowedAssistantLink(url)).toBe(false);
    });
});

function file(name: string, type: string, size = 10): File {
    return new File([new Uint8Array(size)], name, { type });
}

describe('buildPayloadMessages with files', () => {
    it('keeps an empty turn that has file names and emits the names only when present', () => {
        const result = buildPayloadMessages([
            { role: 'user', content: '', attachmentNames: ['a.pdf'] },
            { role: 'assistant', content: 'ok', attachmentNames: [] },
        ]);

        expect(result).toEqual([
            { role: 'user', content: '', attachment_names: ['a.pdf'] },
            { role: 'assistant', content: 'ok' },
        ]);
    });
});

describe('buildAssistantFormData', () => {
    it('emits indexed message keys, names only when present, and each file under attachments[]', () => {
        const a = file('a.jpg', 'image/jpeg');
        const b = file('b.pdf', 'application/pdf');
        const form = buildAssistantFormData(
            'c1',
            [
                { role: 'user', content: '', attachment_names: ['old.pdf'] },
                { role: 'assistant', content: 'ok' },
                { role: 'user', content: 'now' },
            ],
            [a, b],
        );

        expect(form.get('company_id')).toBe('c1');
        expect(form.get('messages[0][role]')).toBe('user');
        expect(form.get('messages[0][content]')).toBe('');
        expect(form.getAll('messages[0][attachment_names][]')).toEqual(['old.pdf']);
        expect(form.has('messages[1][attachment_names][]')).toBe(false);
        expect(form.get('messages[2][content]')).toBe('now');
        expect(form.getAll('attachments[]')).toHaveLength(2);
        expect((form.getAll('attachments[]')[0] as File).name).toBe('a.jpg');
    });

    it('sends no attachments key without files', () => {
        expect(buildAssistantFormData('c1', [], []).has('attachments[]')).toBe(false);
    });
});

describe('assistantAttachmentRejection', () => {
    it('accepts supported types and refuses others', () => {
        expect(assistantAttachmentRejection(file('a.png', 'image/png'), [])).toBeNull();
        expect(assistantAttachmentRejection(file('a.heic', 'image/heic'), [])).toBe('invalid_type');
        expect(assistantAttachmentRejection(file('a.heic', ''), [])).toBe('invalid_type');
        expect(assistantAttachmentRejection(file('a.docx', 'application/msword'), [])).toBe('invalid_type');
    });

    it('falls back to the extension when the type is empty', () => {
        expect(assistantAttachmentRejection(file('scan.PDF', ''), [])).toBeNull();
    });

    it('limits images at 5 MB and PDFs at 10 MB inclusive', () => {
        expect(assistantAttachmentRejection(file('a.jpg', 'image/jpeg', ASSISTANT_IMAGE_MAX_BYTES), [])).toBeNull();
        expect(assistantAttachmentRejection(file('a.jpg', 'image/jpeg', ASSISTANT_IMAGE_MAX_BYTES + 1), [])).toBe(
            'too_large',
        );
        expect(assistantAttachmentRejection(file('a.pdf', 'application/pdf', ASSISTANT_PDF_MAX_BYTES), [])).toBeNull();
        expect(assistantAttachmentRejection(file('a.pdf', 'application/pdf', ASSISTANT_PDF_MAX_BYTES + 1), [])).toBe(
            'too_large',
        );
    });

    it('refuses a sixth file', () => {
        const selected = Array.from({ length: 5 }, () => file('a.jpg', 'image/jpeg'));

        expect(assistantAttachmentRejection(file('b.jpg', 'image/jpeg'), selected)).toBe('too_many');
    });

    it('refuses a file that pushes the total over 12 MB', () => {
        const selected = [file('a.pdf', 'application/pdf', ASSISTANT_PDF_MAX_BYTES)];
        const fits = ASSISTANT_ATTACHMENTS_MAX_TOTAL_BYTES - ASSISTANT_PDF_MAX_BYTES;

        expect(assistantAttachmentRejection(file('b.jpg', 'image/jpeg', fits), selected)).toBeNull();
        expect(assistantAttachmentRejection(file('b.jpg', 'image/jpeg', fits + 1), selected)).toBe('total_too_large');
    });
});

describe('isAttachmentRejection', () => {
    it('is true for 413 and for a 422 on attachments keys', () => {
        expect(isAttachmentRejection(new HttpError(413))).toBe(true);
        expect(isAttachmentRejection(new HttpError(422, null, null, { 'attachments.1': ['x'] }))).toBe(true);
        expect(isAttachmentRejection(new HttpError(422, null, null, { attachments: ['x'] }))).toBe(true);
    });

    it('is false for other errors', () => {
        expect(isAttachmentRejection(new HttpError(422, null, null, { messages: ['x'] }))).toBe(false);
        expect(isAttachmentRejection(new HttpError(500))).toBe(false);
        expect(isAttachmentRejection(new TypeError('network'))).toBe(false);
    });

    it('maps a 413 to the too_large kind', () => {
        expect(describeAssistantError(new HttpError(413)).kind).toBe('too_large');
    });
});
