import { afterEach, describe, expect, it, vi } from 'vitest';
import { HttpError } from '@/utils/http';
import {
    ASSISTANT_ATTACHMENTS_MAX_TOTAL_BYTES,
    ASSISTANT_IMAGE_MAX_BYTES,
    ASSISTANT_PDF_MAX_BYTES,
    assistantAttachmentRejection,
    buildAssistantFormData,
    forgetAllConversations,
    forgetConversation,
    isConversationGone,
    pendingOutcomeOf,
    rememberConversation,
    rememberedConversation,
    toChatMessages,
    describeAssistantError,
    isAllowedAssistantLink,
    isAttachmentRejection,
} from './assistant';

const UUID = '0197a1c2-3b4d-7e5f-8a6b-7c8d9e0f1a2b';

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

describe('buildAssistantFormData', () => {
    const base = { companyId: 'c1', conversationId: null, clientMessageId: 'm1', content: 'hi', files: [] };

    it('emits the flat field set and each file under attachments[]', () => {
        const form = buildAssistantFormData({
            ...base,
            conversationId: 'conv1',
            files: [file('a.jpg', 'image/jpeg'), file('b.pdf', 'application/pdf')],
        });

        expect(Array.from(form.keys())).toEqual([
            'company_id',
            'conversation_id',
            'client_message_id',
            'content',
            'attachments[]',
            'attachments[]',
        ]);
        expect(form.get('conversation_id')).toBe('conv1');
        expect(form.get('client_message_id')).toBe('m1');
        expect((form.getAll('attachments[]')[0] as File).name).toBe('a.jpg');
    });

    it('omits conversation_id when null and attachments without files', () => {
        const form = buildAssistantFormData(base);

        expect(form.has('conversation_id')).toBe(false);
        expect(form.has('attachments[]')).toBe(false);
        expect(Array.from(form.keys()).some((key) => key.startsWith('messages'))).toBe(false);
    });
});

function pending(status: App.Enums.PendingActionStatusEnum): App.Data.Ai.PendingActionData {
    return { id: 'p1', status } as App.Data.Ai.PendingActionData;
}

describe('toChatMessages', () => {
    it('maps roles, attachment names and live pending status', () => {
        const messages = toChatMessages([
            {
                id: 'a',
                role: 'user',
                content: 'x',
                attachment_names: ['a.pdf'],
                actions: [],
                pending_action: null,
                security_notice: null,
                attachment_note: null,
                created_at: '2026-11-13T10:00:00Z',
            },
            {
                id: 'b',
                role: 'assistant',
                content: 'y',
                attachment_names: [],
                actions: [],
                pending_action: pending('executed'),
                security_notice: 'n',
                attachment_note: 'note',
                created_at: '2026-11-13T10:00:01Z',
            },
        ]);

        expect(messages[0]).toMatchObject({ id: 'a', role: 'user', attachmentNames: ['a.pdf'], clientMessageId: null });
        expect(messages[1]).toMatchObject({
            role: 'assistant',
            pendingOutcome: 'done',
            securityNotice: 'n',
            attachmentNote: 'note',
        });
    });

    it.each([
        ['executed', 'done'],
        ['cancelled', 'cancelled'],
        ['pending', null],
        ['expired', null],
    ] as const)('maps pending status %s to %s', (status, outcome) => {
        expect(pendingOutcomeOf(pending(status))).toBe(outcome);
    });

    it('maps no action to null', () => {
        expect(pendingOutcomeOf(null)).toBeNull();
    });
});

describe('isConversationGone', () => {
    it('is true for a 422 with errors.conversation_id only', () => {
        expect(isConversationGone(new HttpError(422, null, null, { conversation_id: ['gone'] }))).toBe(true);
        expect(isConversationGone(new HttpError(422, null, null, { content: ['x'] }))).toBe(false);
        expect(isConversationGone(new HttpError(404))).toBe(false);
        expect(isConversationGone(new Error('x'))).toBe(false);
    });
});

describe('conversation memory', () => {
    afterEach(() => {
        vi.restoreAllMocks();
        window.sessionStorage.clear();
    });

    it('remembers per user and forgets', () => {
        rememberConversation('u1', 'c1');
        rememberConversation('u2', 'c2');

        expect(rememberedConversation('u1')).toBe('c1');
        forgetConversation('u1');
        expect(rememberedConversation('u1')).toBeNull();
        expect(rememberedConversation('u2')).toBe('c2');
    });

    it('forgetAllConversations leaves foreign keys', () => {
        rememberConversation('u1', 'c1');
        rememberConversation('u2', 'c2');
        window.sessionStorage.setItem('other', 'x');

        forgetAllConversations();

        expect(rememberedConversation('u1')).toBeNull();
        expect(rememberedConversation('u2')).toBeNull();
        expect(window.sessionStorage.getItem('other')).toBe('x');
    });

    it('survives a throwing sessionStorage', () => {
        const throwing = {
            setItem: () => {
                throw new Error('quota');
            },
            getItem: () => {
                throw new Error('denied');
            },
            removeItem: () => {
                throw new Error('denied');
            },
            key: () => {
                throw new Error('denied');
            },
            length: 1,
        };
        vi.spyOn(window, 'sessionStorage', 'get').mockReturnValue(throwing as unknown as Storage);

        expect(() => rememberConversation('u1', 'c1')).not.toThrow();
        expect(rememberedConversation('u1')).toBeNull();
        expect(() => forgetConversation('u1')).not.toThrow();
        expect(() => forgetAllConversations()).not.toThrow();
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
