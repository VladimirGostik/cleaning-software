import { describe, expect, it } from 'vitest';
import { HttpError } from '@/utils/http';
import {
    MAX_MESSAGES,
    MAX_MESSAGE_CHARS,
    MAX_TOTAL_CHARS,
    buildPayloadMessages,
    describeAssistantError,
    isAllowedAssistantLink,
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
